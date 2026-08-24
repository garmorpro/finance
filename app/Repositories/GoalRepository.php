<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\FieldCipher;

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
                (household_id, created_by_user_id, responsible_user_id, linked_account_id, name, name_encrypted,
                 description, description_encrypted, goal_type, target_amount, current_amount, target_date,
                 planned_monthly_contribution, status, created_at, updated_at)
             VALUES
                (:household_id, :created_by_user_id, :responsible_user_id, :linked_account_id, NULL, :name_encrypted,
                 NULL, :description_encrypted, :goal_type, :target_amount, 0.00, :target_date,
                 :planned_monthly_contribution, :status, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'created_by_user_id' => $userId,
            'responsible_user_id' => $data['responsible_user_id'],
            'linked_account_id' => $data['linked_account_id'],
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'description_encrypted' => FieldCipher::encrypt($data['description']),
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

        return $row === false ? null : self::hydrate($row);
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
                name = NULL,
                name_encrypted = :name_encrypted,
                description = NULL,
                description_encrypted = :description_encrypted,
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
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'description_encrypted' => FieldCipher::encrypt($data['description']),
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
            'SELECT g.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, u.name AS responsible_name
             FROM financial_goals g
             LEFT JOIN accounts a ON a.id = g.linked_account_id
             LEFT JOIN users u ON u.id = g.responsible_user_id
             WHERE g.household_id = :household_id'
        );

        $stmt->execute(['household_id' => $householdId]);

        $rows = array_map(self::hydrate(...), $stmt->fetchAll());

        // Replicates the original SQL ORDER BY (active first, non-null
        // target_date before null, then target_date, then name) in PHP
        // instead — name is now only decrypted after the fetch, so SQL
        // can no longer sort by it directly.
        usort($rows, function (array $a, array $b): int {
            $rankA = [$a['status'] === 'active' ? 0 : 1, $a['target_date'] === null, $a['target_date'], $a['name']];
            $rankB = [$b['status'] === 'active' ? 0 : 1, $b['target_date'] === null, $b['target_date'], $b['name']];

            return $rankA <=> $rankB;
        });

        return $rows;
    }

    /**
     * Decrypts name/description from their *_encrypted columns, falling
     * back to the old plaintext column for any row not yet run through
     * bin/encrypt-existing-text-fields.php, and (when the row came from
     * listForHousehold()'s JOIN) the linked account's name the same way
     * — see AccountRepository for why a JOIN can no longer read that
     * column directly. Raw *_encrypted binary blobs are removed from the
     * returned row entirely.
     */
    private static function hydrate(array $row): array
    {
        $row['name'] = FieldCipher::decryptOrFallback($row['name_encrypted'], $row['name']);
        $row['description'] = FieldCipher::decryptOrFallback($row['description_encrypted'], $row['description']);
        unset($row['name_encrypted'], $row['description_encrypted']);

        if (array_key_exists('account_name_encrypted', $row)) {
            $row['account_name'] = FieldCipher::decryptOrFallback($row['account_name_encrypted'], $row['account_name']);
            unset($row['account_name_encrypted']);
        }

        return $row;
    }

    /**
     * Sum of goal contributions logged within a date range, for the
     * Budgets page's "Goals" summary — contributions aren't transactions,
     * so they don't show up in any transaction-based total elsewhere.
     */
    public function totalContributionsForRange(int $householdId, string $monthStart, string $monthEndExclusive): string
    {
        $stmt = Connection::get()->prepare(
            'SELECT COALESCE(SUM(gc.amount), 0) AS total
             FROM goal_contributions gc
             INNER JOIN financial_goals g ON g.id = gc.goal_id
             WHERE g.household_id = :household_id
               AND gc.contribution_date >= :month_start AND gc.contribution_date < :month_end'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'month_start' => $monthStart,
            'month_end' => $monthEndExclusive,
        ]);

        return bcadd((string) $stmt->fetchColumn(), '0.00', 2);
    }
}
