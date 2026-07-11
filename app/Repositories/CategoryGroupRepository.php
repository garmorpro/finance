<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class CategoryGroupRepository
{
    public function create(int $householdId, string $name, string $type): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $sortOrder = count($this->listForHousehold($householdId, $type));

        $stmt = Connection::get()->prepare(
            'INSERT INTO category_groups (household_id, name, type, sort_order, created_at, updated_at)
             VALUES (:household_id, :name, :type, :sort_order, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'name' => $name,
            'type' => $type,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $groupId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM category_groups WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $groupId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param string|null $type filter to one type, or null for both (household admin views)
     */
    public function listForHousehold(int $householdId, ?string $type = null): array
    {
        $sql = 'SELECT * FROM category_groups WHERE household_id = :household_id';
        $params = ['household_id' => $householdId];

        if ($type !== null) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY type, sort_order, name';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function rename(int $groupId, int $householdId, string $name): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE category_groups SET name = :name, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'name' => $name,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $groupId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Categories that referenced this group fall back to ungrouped
     * automatically (ON DELETE SET NULL on categories.group_id) — no
     * orphaned references to clean up here.
     */
    public function delete(int $groupId, int $householdId): void
    {
        $stmt = Connection::get()->prepare(
            'DELETE FROM category_groups WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute(['id' => $groupId, 'household_id' => $householdId]);
    }
}
