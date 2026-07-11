<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class TagRepository
{
    public function create(int $householdId, string $name, ?string $color): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO tags (household_id, name, color, created_at) VALUES (:household_id, :name, :color, :created_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'name' => $name,
            'color' => $color,
            'created_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $tagId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM tags WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $tagId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByName(int $householdId, string $name): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM tags WHERE household_id = :household_id AND name = :name LIMIT 1'
        );

        $stmt->execute(['household_id' => $householdId, 'name' => $name]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM tags WHERE household_id = :household_id ORDER BY name'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Includes how many (non-deleted) transactions currently carry each
     * tag, so the settings page can show usage before someone deletes one.
     */
    public function listForHouseholdWithUsage(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, COUNT(tt.transaction_id) AS usage_count
             FROM tags t
             LEFT JOIN transaction_tags tt ON tt.tag_id = t.id
             LEFT JOIN transactions tr ON tr.id = tt.transaction_id AND tr.deleted_at IS NULL
             WHERE t.household_id = :household_id
             GROUP BY t.id
             ORDER BY t.name'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    public function update(int $tagId, int $householdId, string $name, ?string $color): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE tags SET name = :name, color = :color WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'name' => $name,
            'color' => $color,
            'id' => $tagId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Removes every transaction_tags association for this tag before
     * deleting it — a pure join table, so this is a safe, non-destructive
     * cleanup of the tag itself, not of any transaction.
     */
    public function delete(int $tagId, int $householdId): void
    {
        $pdo = Connection::get();

        $unlink = $pdo->prepare('DELETE FROM transaction_tags WHERE tag_id = :tag_id');
        $unlink->execute(['tag_id' => $tagId]);

        $delete = $pdo->prepare('DELETE FROM tags WHERE id = :id AND household_id = :household_id');
        $delete->execute(['id' => $tagId, 'household_id' => $householdId]);
    }

    /**
     * Replaces every tag assignment for a transaction in one go — the
     * caller always sends the complete desired set (from a multi-select),
     * not an incremental add/remove.
     *
     * @param list<int> $tagIds
     */
    public function setTagsForTransaction(int $transactionId, array $tagIds): void
    {
        $pdo = Connection::get();

        $clear = $pdo->prepare('DELETE FROM transaction_tags WHERE transaction_id = :transaction_id');
        $clear->execute(['transaction_id' => $transactionId]);

        if ($tagIds === []) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO transaction_tags (transaction_id, tag_id) VALUES (:transaction_id, :tag_id)');
        foreach (array_unique($tagIds) as $tagId) {
            $insert->execute(['transaction_id' => $transactionId, 'tag_id' => $tagId]);
        }
    }

    /**
     * Adds tags to a transaction without disturbing ones already there —
     * unlike setTagsForTransaction(), this is additive, for rule
     * application where the user's own tag choices at creation time must
     * be preserved alongside whatever a matching rule adds.
     *
     * @param list<int> $tagIds
     */
    public function addTagsToTransaction(int $transactionId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $insert = Connection::get()->prepare(
            'INSERT IGNORE INTO transaction_tags (transaction_id, tag_id) VALUES (:transaction_id, :tag_id)'
        );

        foreach (array_unique($tagIds) as $tagId) {
            $insert->execute(['transaction_id' => $transactionId, 'tag_id' => $tagId]);
        }
    }

    /**
     * @return array<int, list<array>> tags keyed by transaction_id
     */
    public function listForTransactions(array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT tt.transaction_id, t.id, t.name, t.color
             FROM transaction_tags tt
             INNER JOIN tags t ON t.id = tt.tag_id
             WHERE tt.transaction_id IN ({$placeholders})
             ORDER BY t.name"
        );
        $stmt->execute(array_values($transactionIds));

        $byTransaction = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTransaction[(int) $row['transaction_id']][] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
            ];
        }

        return $byTransaction;
    }

    public function listForTransaction(int $transactionId): array
    {
        return $this->listForTransactions([$transactionId])[$transactionId] ?? [];
    }
}
