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

    public function create(int $householdId, string $name, string $type, ?string $color = null, ?int $parentCategoryId = null, ?int $groupId = null): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO categories (household_id, parent_category_id, group_id, name, type, color, sort_order, created_at, updated_at)
             VALUES (:household_id, :parent_category_id, :group_id, :name, :type, :color, 0, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'parent_category_id' => $parentCategoryId,
            'group_id' => $groupId,
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

    public function update(int $categoryId, int $householdId, string $name, string $type, ?string $color, ?int $groupId = null, ?int $parentCategoryId = null): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE categories SET name = :name, type = :type, color = :color, group_id = :group_id,
                parent_category_id = :parent_category_id, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'name' => $name,
            'type' => $type,
            'color' => $color,
            'group_id' => $groupId,
            'parent_category_id' => $parentCategoryId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $categoryId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * True if any other category has this one as its parent — a category
     * with children can't itself become a subcategory (this app supports
     * one level of nesting, not arbitrary depth) and merging one away
     * re-parents its children rather than leaving them dangling.
     */
    public function hasChildren(int $categoryId, int $householdId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM categories WHERE parent_category_id = :parent_category_id AND household_id = :household_id'
        );
        $stmt->execute(['parent_category_id' => $categoryId, 'household_id' => $householdId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Re-parents every child of $fromCategoryId to $toCategoryId — used
     * when merging a category away so its subcategories aren't left
     * pointing at an archived, now-empty parent.
     */
    public function reparentChildren(int $fromCategoryId, int $toCategoryId, int $householdId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE categories SET parent_category_id = :to_id, updated_at = :updated_at
             WHERE parent_category_id = :from_id AND household_id = :household_id'
        );

        $stmt->execute([
            'to_id' => $toCategoryId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'from_id' => $fromCategoryId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Persists a new top-to-bottom sort order for a set of categories —
     * drag-and-drop reordering within one group on the Categories page.
     * Scoped to $householdId and to exactly the ids given, so a client
     * can't smuggle in a category from outside its own household or one
     * that wasn't part of the group being reordered.
     *
     * @param list<int> $orderedCategoryIds
     */
    public function reorder(int $householdId, array $orderedCategoryIds): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE categories SET sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $now = gmdate('Y-m-d H:i:s');
        foreach (array_values($orderedCategoryIds) as $position => $categoryId) {
            $stmt->execute([
                'sort_order' => $position,
                'updated_at' => $now,
                'id' => $categoryId,
                'household_id' => $householdId,
            ]);
        }
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

    public function restore(int $categoryId, int $householdId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE categories SET archived_at = NULL, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $categoryId,
            'household_id' => $householdId,
        ]);
    }
}
