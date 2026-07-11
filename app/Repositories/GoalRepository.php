<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class GoalRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO financial_goals
                (household_id, created_by_user_id, responsible_user_id, linked_account_id, name, description,
                 goal_type, target_amount, current_amount, target_date, planned_monthly_contribution, status,
                 created_at, updated_at)
             VALUES
                (:household_id, :created_by_user_id, :responsible_user_id, :linked_account_id, :name, :description,
                 :goal_type, :target_amount, 0.00, :target_date, :planned_monthly_contribution, :status,
                 :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'created_by_user_id' => $userId,
            'responsible_user_id' => $data['responsible_user_id'],
            'linked_account_id' => $data['linked_account_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'goal_type' => $data['goal_type'],
            'target_amount' => $data['target_amount'],
            'target_date' => $data['target_date'],
            'planned_monthly_contribution' => $data['planned_monthly_contribution'],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $goalId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM financial_goals WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $goalId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $goalId, int $householdId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE financial_goals SET
                responsible_user_id = :responsible_user_id,
                linked_account_id = :linked_account_id,
                name = :name,
                description = :description,
                goal_type = :goal_type,
                target_amount = :target_amount,
                target_date = :target_date,
                planned_monthly_contribution = :planned_monthly_contribution,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'responsible_user_id' => $data['responsible_user_id'],
            'linked_account_id' => $data['linked_account_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'goal_type' => $data['goal_type'],
            'target_amount' => $data['target_amount'],
            'target_date' => $data['target_date'],
            'planned_monthly_contribution' => $data['planned_monthly_contribution'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $goalId,
            'household_id' => $householdId,
        ]);
    }

    public function setStatus(int $goalId, int $householdId, string $status): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE financial_goals SET status = :status, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $goalId,
            'household_id' => $householdId,
        ]);
    }

    private function adjustCurrentAmount(int $goalId, int $householdId, string $delta): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE financial_goals SET current_amount = current_amount + :delta, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'delta' => $delta,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $goalId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Records a contribution and updates the goal's running current_amount
     * in the same call. The caller should still wrap this in a DB
     * transaction (as the controller does) so the insert and the balance
     * update can never partially apply.
     */
    public function addContribution(int $goalId, int $householdId, int $userId, string $amount, string $date, ?string $notes): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO goal_contributions (goal_id, user_id, amount, contribution_date, notes, created_at)
             VALUES (:goal_id, :user_id, :amount, :contribution_date, :notes, :created_at)'
        );

        $stmt->execute([
            'goal_id' => $goalId,
            'user_id' => $userId,
            'amount' => $amount,
            'contribution_date' => $date,
            'notes' => $notes,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $contributionId = (int) Connection::get()->lastInsertId();

        $this->adjustCurrentAmount($goalId, $householdId, $amount);

        return $contributionId;
    }

    /**
     * Scoped through financial_goals.household_id, and reverses the
     * contribution's amount out of current_amount — deleting a
     * contribution should never leave the running total including money
     * that's no longer logged.
     */
    public function deleteContribution(int $contributionId, int $goalId, int $householdId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT gc.amount FROM goal_contributions gc
             INNER JOIN financial_goals g ON g.id = gc.goal_id
             WHERE gc.id = :id AND gc.goal_id = :goal_id AND g.household_id = :household_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $contributionId, 'goal_id' => $goalId, 'household_id' => $householdId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return false;
        }

        $delete = Connection::get()->prepare('DELETE FROM goal_contributions WHERE id = :id');
        $delete->execute(['id' => $contributionId]);

        $this->adjustCurrentAmount($goalId, $householdId, bcmul($row['amount'], '-1', 2));

        return true;
    }

    public function listContributions(int $goalId, int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT gc.*, u.name AS user_name
             FROM goal_contributions gc
             INNER JOIN financial_goals g ON g.id = gc.goal_id
             INNER JOIN users u ON u.id = gc.user_id
             WHERE gc.goal_id = :goal_id AND g.household_id = :household_id
             ORDER BY gc.contribution_date DESC, gc.id DESC'
        );

        $stmt->execute(['goal_id' => $goalId, 'household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    public function listForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT g.*, a.name AS account_name, u.name AS responsible_name
             FROM financial_goals g
             LEFT JOIN accounts a ON a.id = g.linked_account_id
             LEFT JOIN users u ON u.id = g.responsible_user_id
             WHERE g.household_id = :household_id
             ORDER BY (g.status = "active") DESC, g.target_date IS NULL, g.target_date, g.name'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }
}
