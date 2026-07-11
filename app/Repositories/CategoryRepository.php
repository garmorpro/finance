<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class CategoryRepository
{
    private const DEFAULT_EXPENSE_CATEGORIES = [
        'Housing', 'Utilities', 'Groceries', 'Restaurants', 'Transportation',
        'Insurance', 'Healthcare', 'Shopping', 'Entertainment', 'Travel',
        'Pets', 'Childcare', 'Gifts', 'Education', 'Debt Payments',
        'Savings', 'Investments',
    ];

    private const DEFAULT_INCOME_CATEGORIES = [
        'Salary', 'Side Income', 'Refunds', 'Other Income',
    ];

    /**
     * Seeds a starter category set for a newly created household, per
     * CLAUDE.md's example category list. Users can rename/archive freely
     * afterward — this just avoids an empty category picker on day one.
     */
    public function seedDefaults(int $householdId): void
    {
        foreach (self::DEFAULT_EXPENSE_CATEGORIES as $name) {
            $this->create($householdId, $name, 'expense');
        }

        foreach (self::DEFAULT_INCOME_CATEGORIES as $name) {
            $this->create($householdId, $name, 'income');
        }
    }

    public function create(int $householdId, string $name, string $type, ?string $color = null, ?int $parentCategoryId = null): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO categories (household_id, parent_category_id, name, type, color, sort_order, created_at, updated_at)
             VALUES (:household_id, :parent_category_id, :name, :type, :color, 0, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'parent_category_id' => $parentCategoryId,
            'name' => $name,
            'type' => $type,
            'color' => $color,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $categoryId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM categories WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $categoryId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForHousehold(int $householdId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM categories WHERE household_id = :household_id';

        if (!$includeArchived) {
            $sql .= ' AND archived_at IS NULL';
        }

        $sql .= ' ORDER BY type, sort_order, name';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    public function archive(int $categoryId, int $householdId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE categories SET archived_at = :archived_at, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'archived_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $categoryId,
            'household_id' => $householdId,
        ]);
    }
}
