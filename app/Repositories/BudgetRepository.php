<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class BudgetRepository
{
    public function findForMonth(int $householdId, string $periodMonth): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM budgets WHERE household_id = :household_id AND period_month = :period_month LIMIT 1'
        );

        $stmt->execute(['household_id' => $householdId, 'period_month' => $periodMonth]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Budgets are only ever created lazily, the first time a category gets
     * a planned amount for that month — visiting an empty month never
     * writes a row.
     */
    public function findOrCreateForMonth(int $householdId, string $periodMonth): array
    {
        $existing = $this->findForMonth($householdId, $periodMonth);
        if ($existing !== null) {
            return $existing;
        }

        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO budgets (household_id, period_month, created_at, updated_at)
             VALUES (:household_id, :period_month, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'period_month' => $periodMonth,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) Connection::get()->lastInsertId();

        return [
            'id' => $id,
            'household_id' => $householdId,
            'period_month' => $periodMonth,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Planned amounts for a budget, joined with category display info.
     * Only expense categories are ever budgeted, per the MVP scope.
     */
    public function listItemsForBudget(int $budgetId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT bi.*, c.name AS category_name, c.color AS category_color
             FROM budget_items bi
             INNER JOIN categories c ON c.id = bi.category_id
             WHERE bi.budget_id = :budget_id
             ORDER BY c.sort_order, c.name'
        );

        $stmt->execute(['budget_id' => $budgetId]);

        return $stmt->fetchAll();
    }

    public function upsertItem(int $budgetId, int $categoryId, string $plannedAmount): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO budget_items (budget_id, category_id, planned_amount, created_at, updated_at)
             VALUES (:budget_id, :category_id, :planned_amount, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE planned_amount = :planned_amount_update, updated_at = :updated_at_update'
        );

        $stmt->execute([
            'budget_id' => $budgetId,
            'category_id' => $categoryId,
            'planned_amount' => $plannedAmount,
            'created_at' => $now,
            'updated_at' => $now,
            'planned_amount_update' => $plannedAmount,
            'updated_at_update' => $now,
        ]);
    }

    public function deleteItem(int $budgetId, int $categoryId): void
    {
        $stmt = Connection::get()->prepare(
            'DELETE FROM budget_items WHERE budget_id = :budget_id AND category_id = :category_id'
        );

        $stmt->execute(['budget_id' => $budgetId, 'category_id' => $categoryId]);
    }

    /**
     * Copies every planned amount from one budget to another, skipping any
     * category the target budget already has a line for — "copy last
     * month" is meant to fill in a fresh month, not overwrite edits the
     * user already made to the target month.
     *
     * @return int number of lines copied
     */
    public function copyItemsFrom(int $sourceBudgetId, int $targetBudgetId): int
    {
        $sourceItems = $this->listItemsForBudget($sourceBudgetId);
        $targetCategoryIds = array_column($this->listItemsForBudget($targetBudgetId), 'category_id');

        $copied = 0;
        foreach ($sourceItems as $item) {
            if (in_array($item['category_id'], $targetCategoryIds, true)) {
                continue;
            }

            $this->upsertItem($targetBudgetId, (int) $item['category_id'], $item['planned_amount']);
            $copied++;
        }

        return $copied;
    }

    /**
     * Actual spending per expense category for the given month, keyed by
     * category_id, as a positive decimal string (transactions store
     * expenses as negative amounts). Excludes transfers, soft-deleted
     * transactions, and anything explicitly marked exclude_from_budget —
     * matching how the rest of the app treats those transactions. Includes
     * every expense category with activity, not just budgeted ones, so the
     * caller can surface unbudgeted spending.
     *
     * @return array<int, string>
     */
    public function actualSpendingByCategory(int $householdId, string $monthStart, string $monthEndExclusive): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT category_id, amount FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND exclude_from_budget = 0
               AND transaction_type = "expense" AND category_id IS NOT NULL
               AND transaction_date >= :month_start AND transaction_date < :month_end'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'month_start' => $monthStart,
            'month_end' => $monthEndExclusive,
        ]);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            $spent = bcmul($row['amount'], '-1', 2);
            $totals[$categoryId] = bcadd($totals[$categoryId] ?? '0.00', $spent, 2);
        }

        return $totals;
    }

    /**
     * Aggregate planned/spent/remaining for a month, for callers (like the
     * dashboard tile) that only need the totals, not the per-category
     * breakdown that index() builds for the full budget page.
     *
     * @return array{planned: string, spent: string, remaining: string, has_items: bool}
     */
    public function monthSummary(int $householdId, string $periodMonth): array
    {
        $budget = $this->findForMonth($householdId, $periodMonth);
        $items = $budget !== null ? $this->listItemsForBudget((int) $budget['id']) : [];

        if ($items === []) {
            return ['planned' => '0.00', 'spent' => '0.00', 'remaining' => '0.00', 'has_items' => false];
        }

        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));
        $actualByCategory = $this->actualSpendingByCategory($householdId, $periodMonth, $monthEnd);

        $planned = '0.00';
        $spent = '0.00';
        foreach ($items as $item) {
            $planned = bcadd($planned, $item['planned_amount'], 2);
            $spent = bcadd($spent, $actualByCategory[(int) $item['category_id']] ?? '0.00', 2);
        }

        return [
            'planned' => $planned,
            'spent' => $spent,
            'remaining' => bcsub($planned, $spent, 2),
            'has_items' => true,
        ];
    }
}
