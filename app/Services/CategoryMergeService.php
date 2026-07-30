<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Repositories\CategoryRepository;
use PDO;

/**
 * Consolidates one category into another. Every transaction, split,
 * recurring item, budget line, standing monthly default, and
 * category-assigning rule action that pointed at the source category is
 * repointed at the target; any subcategories of the source are
 * re-parented to the target instead of being left pointing at an
 * archived parent; the source category itself is archived (never hard
 * deleted) once it's empty, the same way any other category is retired.
 */
final class CategoryMergeService
{
    /**
     * Counts of what a merge would move, shown for confirmation before
     * anything is touched — same "preview the affected count first"
     * requirement CLAUDE.md asks for on retroactive rule changes.
     *
     * @return array{transactions: int, splits: int, recurringItems: int, ruleActions: int, budgetItems: int, subcategories: int}
     */
    public function preview(int $sourceCategoryId, int $householdId): array
    {
        $pdo = Connection::get();

        return [
            'transactions' => $this->count($pdo,
                'SELECT COUNT(*) FROM transactions WHERE category_id = :category_id AND household_id = :household_id AND deleted_at IS NULL',
                ['category_id' => $sourceCategoryId, 'household_id' => $householdId]
            ),
            'splits' => $this->count($pdo,
                'SELECT COUNT(*) FROM transaction_splits ts INNER JOIN transactions t ON t.id = ts.transaction_id
                 WHERE ts.category_id = :category_id AND t.household_id = :household_id AND t.deleted_at IS NULL',
                ['category_id' => $sourceCategoryId, 'household_id' => $householdId]
            ),
            'recurringItems' => $this->count($pdo,
                'SELECT COUNT(*) FROM recurring_items WHERE category_id = :category_id AND household_id = :household_id',
                ['category_id' => $sourceCategoryId, 'household_id' => $householdId]
            ),
            'ruleActions' => $this->count($pdo,
                'SELECT COUNT(*) FROM transaction_rule_actions tra INNER JOIN transaction_rules tr ON tr.id = tra.rule_id
                 WHERE tra.action_type = "set_category" AND tra.value = :category_id AND tr.household_id = :household_id',
                ['category_id' => (string) $sourceCategoryId, 'household_id' => $householdId]
            ),
            'budgetItems' => $this->count($pdo,
                'SELECT COUNT(*) FROM budget_items bi INNER JOIN budgets b ON b.id = bi.budget_id
                 WHERE bi.category_id = :category_id AND b.household_id = :household_id',
                ['category_id' => $sourceCategoryId, 'household_id' => $householdId]
            ),
            'subcategories' => $this->count($pdo,
                'SELECT COUNT(*) FROM categories WHERE parent_category_id = :category_id AND household_id = :household_id',
                ['category_id' => $sourceCategoryId, 'household_id' => $householdId]
            ),
        ];
    }

    /**
     * Performs the merge inside its own transaction. The caller is
     * responsible for verifying beforehand that both categories belong to
     * this household, are the same type, and aren't the same category —
     * this only moves data.
     */
    public function merge(int $sourceCategoryId, int $targetCategoryId, int $householdId): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            (new CategoryRepository())->reparentChildren($sourceCategoryId, $targetCategoryId, $householdId);

            $this->exec($pdo,
                'UPDATE transactions SET category_id = :target_id WHERE category_id = :source_id AND household_id = :household_id',
                ['target_id' => $targetCategoryId, 'source_id' => $sourceCategoryId, 'household_id' => $householdId]
            );

            $this->exec($pdo,
                'UPDATE transaction_splits ts INNER JOIN transactions t ON t.id = ts.transaction_id
                 SET ts.category_id = :target_id
                 WHERE ts.category_id = :source_id AND t.household_id = :household_id',
                ['target_id' => $targetCategoryId, 'source_id' => $sourceCategoryId, 'household_id' => $householdId]
            );

            $this->exec($pdo,
                'UPDATE recurring_items SET category_id = :target_id WHERE category_id = :source_id AND household_id = :household_id',
                ['target_id' => $targetCategoryId, 'source_id' => $sourceCategoryId, 'household_id' => $householdId]
            );

            $this->exec($pdo,
                'UPDATE transaction_rule_actions tra INNER JOIN transaction_rules tr ON tr.id = tra.rule_id
                 SET tra.value = :target_id
                 WHERE tra.action_type = "set_category" AND tra.value = :source_id AND tr.household_id = :household_id',
                ['target_id' => (string) $targetCategoryId, 'source_id' => (string) $sourceCategoryId, 'household_id' => $householdId]
            );

            $this->mergeBudgetItems($pdo, $sourceCategoryId, $targetCategoryId, $householdId);
            $this->mergeBudgetDefaults($pdo, $sourceCategoryId, $targetCategoryId, $householdId);

            (new CategoryRepository())->archive($sourceCategoryId, $householdId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function count(PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function exec(PDO $pdo, string $sql, array $params): void
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * budget_items has a UNIQUE(budget_id, category_id) constraint, so a
     * blind UPDATE would collide whenever the source and target category
     * both already have a planned line in the same month's budget. When
     * that happens the two planned amounts are added together into the
     * target's row and the source's row is removed, rather than either
     * silently overwriting the other.
     */
    private function mergeBudgetItems(PDO $pdo, int $sourceId, int $targetId, int $householdId): void
    {
        $stmt = $pdo->prepare(
            'SELECT bi.id, bi.budget_id, bi.planned_amount
             FROM budget_items bi INNER JOIN budgets b ON b.id = bi.budget_id
             WHERE bi.category_id = :source_id AND b.household_id = :household_id'
        );
        $stmt->execute(['source_id' => $sourceId, 'household_id' => $householdId]);

        foreach ($stmt->fetchAll() as $row) {
            $existing = $pdo->prepare('SELECT id, planned_amount FROM budget_items WHERE budget_id = :budget_id AND category_id = :target_id');
            $existing->execute(['budget_id' => $row['budget_id'], 'target_id' => $targetId]);
            $targetRow = $existing->fetch();

            if ($targetRow === false) {
                $update = $pdo->prepare('UPDATE budget_items SET category_id = :target_id WHERE id = :id');
                $update->execute(['target_id' => $targetId, 'id' => $row['id']]);
                continue;
            }

            $combined = bcadd($row['planned_amount'], $targetRow['planned_amount'], 2);
            $updateTarget = $pdo->prepare('UPDATE budget_items SET planned_amount = :planned_amount WHERE id = :id');
            $updateTarget->execute(['planned_amount' => $combined, 'id' => $targetRow['id']]);

            $deleteSource = $pdo->prepare('DELETE FROM budget_items WHERE id = :id');
            $deleteSource->execute(['id' => $row['id']]);
        }
    }

    /**
     * Same UNIQUE(household_id, category_id) collision as budget items,
     * but a standing default isn't additive the way a month's plan is —
     * if the target already has one it wins and the source's is simply
     * dropped; otherwise the source's default carries over to the target.
     */
    private function mergeBudgetDefaults(PDO $pdo, int $sourceId, int $targetId, int $householdId): void
    {
        $existing = $pdo->prepare('SELECT id FROM budget_category_defaults WHERE household_id = :household_id AND category_id = :target_id');
        $existing->execute(['household_id' => $householdId, 'target_id' => $targetId]);

        if ($existing->fetch() !== false) {
            $delete = $pdo->prepare('DELETE FROM budget_category_defaults WHERE household_id = :household_id AND category_id = :source_id');
            $delete->execute(['household_id' => $householdId, 'source_id' => $sourceId]);
            return;
        }

        $update = $pdo->prepare('UPDATE budget_category_defaults SET category_id = :target_id WHERE household_id = :household_id AND category_id = :source_id');
        $update->execute(['target_id' => $targetId, 'household_id' => $householdId, 'source_id' => $sourceId]);
    }
}
