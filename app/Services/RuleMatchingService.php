<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TagRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionRuleRepository;

/**
 * Matches transaction rules against transactions and applies their
 * actions. Rules never touch transfers — their payee/notes are
 * system-generated ("Transfer to X") and both legs must stay in sync,
 * which a categorization rule has no business reasoning about.
 *
 * A rule's conditions are always ANDed (every condition must match). A
 * transaction can match several rules; their actions layer in rule order —
 * a later rule's category/payee override an earlier one's, tags from every
 * matching rule accumulate, and the hide-from-reports flag only ever
 * turns on, never back off.
 */
final class RuleMatchingService
{
    public function matchesRule(array $conditions, array $transaction): bool
    {
        if ($conditions === [] || $transaction['transaction_type'] === 'transfer') {
            return false;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $transaction)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{category_id: ?int, payee: ?string, tag_ids: list<int>, exclude_from_reports: bool}
     */
    public function resolveActions(array $actions): array
    {
        $result = ['category_id' => null, 'payee' => null, 'tag_ids' => [], 'exclude_from_reports' => false];

        foreach ($actions as $action) {
            switch ($action['action_type']) {
                case 'set_category':
                    $result['category_id'] = (int) $action['value'];
                    break;
                case 'set_payee':
                    $result['payee'] = $action['value'];
                    break;
                case 'add_tag':
                    $result['tag_ids'][] = (int) $action['value'];
                    break;
                case 'exclude_from_reports':
                    $result['exclude_from_reports'] = true;
                    break;
            }
        }

        return $result;
    }

    /**
     * Runs every active rule against one freshly created (or edited)
     * transaction. Called from every place a transaction is created:
     * manual entry, CSV import, and recurring "mark paid" — never for
     * transfers, which are excluded above.
     */
    public function applyToTransaction(int $householdId, int $transactionId, array $transaction): void
    {
        if ($transaction['transaction_type'] === 'transfer') {
            return;
        }

        $accumulated = ['category_id' => null, 'payee' => null, 'tag_ids' => [], 'exclude_from_reports' => false];
        $matchedAny = false;

        foreach ((new TransactionRuleRepository())->listActiveWithDetails($householdId) as $entry) {
            if (!$this->matchesRule($entry['conditions'], $transaction)) {
                continue;
            }

            $matchedAny = true;
            $actions = $this->resolveActions($entry['actions']);

            $accumulated['category_id'] = $actions['category_id'] ?? $accumulated['category_id'];
            $accumulated['payee'] = $actions['payee'] ?? $accumulated['payee'];
            $accumulated['tag_ids'] = [...$accumulated['tag_ids'], ...$actions['tag_ids']];
            $accumulated['exclude_from_reports'] = $accumulated['exclude_from_reports'] || $actions['exclude_from_reports'];
        }

        if (!$matchedAny) {
            return;
        }

        $this->applyResolvedActions($householdId, $transactionId, $accumulated);
    }

    /**
     * How many existing (non-deleted, non-transfer) transactions a rule's
     * conditions currently match — shown before a retroactive apply, per
     * CLAUDE.md's requirement to preview the affected count and require
     * confirmation before touching transaction history.
     *
     * @param list<array{field: string, operator: string, value: string}> $conditions
     */
    public function previewCount(int $householdId, array $conditions): int
    {
        $count = 0;

        foreach ((new TransactionRepository())->listMatchableForHousehold($householdId) as $transaction) {
            if ($this->matchesRule($conditions, $transaction)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The confirmed counterpart to previewCount() — applies a rule's
     * actions to every existing matching transaction. Returns how many
     * were actually touched.
     *
     * @param list<array{field: string, operator: string, value: string}> $conditions
     * @param list<array{action_type: string, value: string|null}> $actions
     */
    public function applyToExisting(int $householdId, array $conditions, array $actions): int
    {
        $resolved = $this->resolveActions($actions);
        $touched = 0;

        foreach ((new TransactionRepository())->listMatchableForHousehold($householdId) as $transaction) {
            if (!$this->matchesRule($conditions, $transaction)) {
                continue;
            }

            $this->applyResolvedActions($householdId, (int) $transaction['id'], $resolved);
            $touched++;
        }

        return $touched;
    }

    /**
     * @param array{category_id: ?int, payee: ?string, tag_ids: list<int>, exclude_from_reports: bool} $resolved
     */
    private function applyResolvedActions(int $householdId, int $transactionId, array $resolved): void
    {
        (new TransactionRepository())->applyRuleActions(
            $transactionId,
            $householdId,
            $resolved['category_id'],
            $resolved['payee'],
            $resolved['exclude_from_reports']
        );

        if ($resolved['tag_ids'] !== []) {
            (new TagRepository())->addTagsToTransaction($transactionId, $resolved['tag_ids']);
        }
    }

    private function evaluateCondition(array $condition, array $transaction): bool
    {
        return match ($condition['field']) {
            'payee' => $this->textMatch($condition['operator'], $transaction['payee'] ?? '', $condition['value']),
            'notes' => $this->textMatch($condition['operator'], $transaction['notes'] ?? '', $condition['value']),
            'amount' => $this->amountMatch($condition['operator'], $transaction['amount'] ?? '0.00', $condition['value']),
            'account_id' => (string) $transaction['account_id'] === $condition['value'],
            'transaction_type' => $transaction['transaction_type'] === $condition['value'],
            default => false,
        };
    }

    private function textMatch(string $operator, string $actual, string $value): bool
    {
        return match ($operator) {
            'contains' => $value !== '' && stripos($actual, $value) !== false,
            'equals' => strcasecmp($actual, $value) === 0,
            default => false,
        };
    }

    private function amountMatch(string $operator, string $actualSigned, string $value): bool
    {
        $actual = ltrim($actualSigned, '-');

        return match ($operator) {
            'equals' => bccomp($actual, $value, 2) === 0,
            'greater_than' => bccomp($actual, $value, 2) > 0,
            'less_than' => bccomp($actual, $value, 2) < 0,
            default => false,
        };
    }
}
