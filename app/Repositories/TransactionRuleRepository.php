<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class TransactionRuleRepository
{
    public function create(int $householdId, int $userId, string $name): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $sortOrder = count($this->listForHousehold($householdId));

        $stmt = Connection::get()->prepare(
            'INSERT INTO transaction_rules (household_id, created_by_user_id, name, is_active, sort_order, created_at, updated_at)
             VALUES (:household_id, :created_by_user_id, :name, 1, :sort_order, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'created_by_user_id' => $userId,
            'name' => $name,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $ruleId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM transaction_rules WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $ruleId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM transaction_rules WHERE household_id = :household_id ORDER BY sort_order, name'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Active rules with their conditions and actions eager-loaded, for
     * both live application (new transactions) and the preview/apply-to-
     * existing flow. N+1 by rule count, which is fine — a household has a
     * handful of rules, not thousands.
     *
     * @return list<array{rule: array, conditions: list<array>, actions: list<array>}>
     */
    public function listActiveWithDetails(int $householdId): array
    {
        $rules = array_values(array_filter(
            $this->listForHousehold($householdId),
            fn (array $rule): bool => (int) $rule['is_active'] === 1
        ));

        $result = [];
        foreach ($rules as $rule) {
            $ruleId = (int) $rule['id'];
            $result[] = [
                'rule' => $rule,
                'conditions' => $this->listConditions($ruleId),
                'actions' => $this->listActions($ruleId),
            ];
        }

        return $result;
    }

    public function listConditions(int $ruleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM transaction_rule_conditions WHERE rule_id = :rule_id ORDER BY id'
        );
        $stmt->execute(['rule_id' => $ruleId]);

        return $stmt->fetchAll();
    }

    public function listActions(int $ruleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM transaction_rule_actions WHERE rule_id = :rule_id ORDER BY id'
        );
        $stmt->execute(['rule_id' => $ruleId]);

        return $stmt->fetchAll();
    }

    public function rename(int $ruleId, int $householdId, string $name): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transaction_rules SET name = :name, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'name' => $name,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $ruleId,
            'household_id' => $householdId,
        ]);
    }

    public function setActive(int $ruleId, int $householdId, bool $active): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transaction_rules SET is_active = :is_active, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'is_active' => $active ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $ruleId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * @param list<array{field: string, operator: string, value: string}> $conditions
     */
    public function replaceConditions(int $ruleId, array $conditions): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM transaction_rule_conditions WHERE rule_id = :rule_id')->execute(['rule_id' => $ruleId]);

        $insert = $pdo->prepare(
            'INSERT INTO transaction_rule_conditions (rule_id, field, operator, value) VALUES (:rule_id, :field, :operator, :value)'
        );

        foreach ($conditions as $condition) {
            $insert->execute([
                'rule_id' => $ruleId,
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
            ]);
        }
    }

    /**
     * @param list<array{action_type: string, value: string|null}> $actions
     */
    public function replaceActions(int $ruleId, array $actions): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM transaction_rule_actions WHERE rule_id = :rule_id')->execute(['rule_id' => $ruleId]);

        $insert = $pdo->prepare(
            'INSERT INTO transaction_rule_actions (rule_id, action_type, value) VALUES (:rule_id, :action_type, :value)'
        );

        foreach ($actions as $action) {
            $insert->execute([
                'rule_id' => $ruleId,
                'action_type' => $action['action_type'],
                'value' => $action['value'],
            ]);
        }
    }

    /**
     * Manual cascade — this app doesn't use ON DELETE CASCADE anywhere, so
     * children are removed explicitly before the parent row.
     */
    public function delete(int $ruleId, int $householdId): void
    {
        $pdo = Connection::get();

        $pdo->prepare('DELETE FROM transaction_rule_conditions WHERE rule_id = :rule_id')->execute(['rule_id' => $ruleId]);
        $pdo->prepare('DELETE FROM transaction_rule_actions WHERE rule_id = :rule_id')->execute(['rule_id' => $ruleId]);
        $pdo->prepare('DELETE FROM transaction_rules WHERE id = :id AND household_id = :household_id')
            ->execute(['id' => $ruleId, 'household_id' => $householdId]);
    }
}
