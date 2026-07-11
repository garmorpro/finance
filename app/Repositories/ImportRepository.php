<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class ImportRepository
{
    public function create(
        int $householdId,
        int $userId,
        int $accountId,
        string $filename,
        int $rowCount,
        int $importedCount,
        int $skippedCount,
        int $rejectedCount
    ): int {
        $stmt = Connection::get()->prepare(
            'INSERT INTO imports
                (household_id, imported_by_user_id, account_id, filename, row_count, imported_count, skipped_count, rejected_count, created_at)
             VALUES
                (:household_id, :imported_by_user_id, :account_id, :filename, :row_count, :imported_count, :skipped_count, :rejected_count, :created_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'imported_by_user_id' => $userId,
            'account_id' => $accountId,
            'filename' => $filename,
            'row_count' => $rowCount,
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'rejected_count' => $rejectedCount,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $importId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM imports WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $importId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT i.*, a.name AS account_name, u.name AS imported_by_name
             FROM imports i
             INNER JOIN accounts a ON a.id = i.account_id
             INNER JOIN users u ON u.id = i.imported_by_user_id
             WHERE i.household_id = :household_id
             ORDER BY i.created_at DESC
             LIMIT 20'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }
}
