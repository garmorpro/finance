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
     * Budgets are created lazily, the first time a month is either edited
     * or simply viewed — creation immediately seeds it from any standing
     * category defaults (see upsertDefault()), so "how much can I budget
     * this month" is answered on arrival instead of requiring the user to
     * touch something first.
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

        foreach ($this->defaultsForHousehold($householdId) as $categoryId => $plannedAmount) {
            $this->upsertItem($id, $categoryId, $plannedAmount);
        }

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
     * The household's standing "apply to all future months" planned
     * amounts, one per category — a fresh month's budget is seeded from
     * this the moment it's created. Not itself month-scoped: checking the
     * box updates this row, and it applies until someone changes it again.
     *
     * @return array<int, string>
     */
    public function defaultsForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT category_id, planned_amount FROM budget_category_defaults WHERE household_id = :household_id'
        );

        $stmt->execute(['household_id' => $householdId]);

        $defaults = [];
        foreach ($stmt->fetchAll() as $row) {
            $defaults[(int) $row['category_id']] = $row['planned_amount'];
        }

        return $defaults;
    }

    public function upsertDefault(int $householdId, int $categoryId, string $plannedAmount): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO budget_category_defaults (household_id, category_id, planned_amount, created_at, updated_at)
             VALUES (:household_id, :category_id, :planned_amount, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE planned_amount = :planned_amount_update, updated_at = :updated_at_update'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'category_id' => $categoryId,
            'planned_amount' => $plannedAmount,
            'created_at' => $now,
            'updated_at' => $now,
            'planned_amount_update' => $plannedAmount,
            'updated_at_update' => $now,
        ]);
    }

    /**
     * Planned amounts for a budget, joined with category display info
     * (including type and group, so callers can split/bucket items
     * without a second categories lookup). Covers both income and
     * expense categories — a single budget can plan both.
     */
    public function listItemsForBudget(int $budgetId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT bi.*, c.name AS category_name, c.color AS category_color,
                    c.type AS category_type, c.group_id AS category_group_id
             FROM budget_items bi
             INNER JOIN categories c ON c.id = bi.category_id
             WHERE bi.budget_id = :budget_id
             ORDER BY c.type, c.sort_order, c.name'
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
     * Actual activity per category of the given type for the given month,
     * keyed by category_id, as a positive decimal string — expense
     * transactions are stored negative and get flipped, income
     * transactions are already positive. Excludes transfers, soft-deleted
     * transactions, and anything explicitly marked exclude_from_budget,
     * matching how the rest of the app treats those. Includes every
     * category with activity, not just budgeted ones, so the caller can
     * surface unbudgeted amounts.
     *
     * A split transaction's own category_id is just a "primary" display
     * category (the largest split line — see TransactionController); the
     * actual per-category total here has to come from each split's own
     * line item instead, or a split purchase would misreport its whole
     * amount against just the primary category. Unsplit transactions
     * (the overwhelming majority) are read directly, unchanged.
     *
     * @return array<int, string>
     */
    public function actualByCategory(int $householdId, string $transactionType, string $monthStart, string $monthEndExclusive): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT category_id, amount FROM (
                SELECT t.category_id AS category_id, t.amount AS amount
                FROM transactions t
                WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.exclude_from_budget = 0
                  AND t.transaction_type = :transaction_type AND t.is_split = 0
                  AND t.transaction_date >= :month_start AND t.transaction_date < :month_end
                UNION ALL
                SELECT ts.category_id AS category_id, ts.amount AS amount
                FROM transactions t
                INNER JOIN transaction_splits ts ON ts.transaction_id = t.id
                WHERE t.household_id = :household_id2 AND t.deleted_at IS NULL AND t.exclude_from_budget = 0
                  AND t.transaction_type = :transaction_type2 AND t.is_split = 1
                  AND t.transaction_date >= :month_start2 AND t.transaction_date < :month_end2
            ) combined
            WHERE category_id IS NOT NULL'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'transaction_type' => $transactionType,
            'month_start' => $monthStart,
            'month_end' => $monthEndExclusive,
            'household_id2' => $householdId,
            'transaction_type2' => $transactionType,
            'month_start2' => $monthStart,
            'month_end2' => $monthEndExclusive,
        ]);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            $amount = $transactionType === 'expense' ? bcmul($row['amount'], '-1', 2) : $row['amount'];
            $totals[$categoryId] = bcadd($totals[$categoryId] ?? '0.00', $amount, 2);
        }

        return $totals;
    }

    /**
     * Actual activity for a single category over a trailing window of
     * whole months, keyed by "Y-m" and zero-filled for months with no
     * activity — feeds the Budgets page's per-category history popover
     * (last month spent, monthly average). $beforeMonth is the first day
     * of the currently-viewed month, exclusive, so the window is always
     * whole, already-completed months.
     *
     * @return array<string, string>
     */
    public function actualsByCategoryTrailingMonths(int $householdId, int $categoryId, string $categoryType, string $beforeMonth, int $monthsBack): array
    {
        $rangeStart = date('Y-m-d', strtotime($beforeMonth . " -{$monthsBack} months"));

        $stmt = Connection::get()->prepare(
            'SELECT ym, SUM(amount) AS amount FROM (
                SELECT DATE_FORMAT(t.transaction_date, "%Y-%m") AS ym, t.amount AS amount
                FROM transactions t
                WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.exclude_from_budget = 0
                  AND t.category_id = :category_id AND t.is_split = 0
                  AND t.transaction_date >= :range_start AND t.transaction_date < :range_end
                UNION ALL
                SELECT DATE_FORMAT(t.transaction_date, "%Y-%m") AS ym, ts.amount AS amount
                FROM transactions t
                INNER JOIN transaction_splits ts ON ts.transaction_id = t.id
                WHERE t.household_id = :household_id2 AND t.deleted_at IS NULL AND t.exclude_from_budget = 0
                  AND ts.category_id = :category_id2 AND t.is_split = 1
                  AND t.transaction_date >= :range_start2 AND t.transaction_date < :range_end2
            ) combined
            GROUP BY ym'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'category_id' => $categoryId,
            'range_start' => $rangeStart,
            'range_end' => $beforeMonth,
            'household_id2' => $householdId,
            'category_id2' => $categoryId,
            'range_start2' => $rangeStart,
            'range_end2' => $beforeMonth,
        ]);

        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $amount = $categoryType === 'expense' ? bcmul($row['amount'], '-1', 2) : $row['amount'];
            $byMonth[$row['ym']] = $amount;
        }

        $result = [];
        for ($i = $monthsBack; $i >= 1; $i--) {
            $ym = date('Y-m', strtotime($beforeMonth . " -{$i} months"));
            $result[$ym] = $byMonth[$ym] ?? '0.00';
        }

        return $result;
    }

    /**
     * Aggregate planned/spent/remaining for a month's expense budget only
     * (income isn't part of this "remaining" framing), for callers like
     * the dashboard tile that only need the totals, not the per-category
     * breakdown that index() builds for the full budget page.
     *
     * @return array{planned: string, spent: string, remaining: string, has_items: bool}
     */
    public function monthSummary(int $householdId, string $periodMonth): array
    {
        $budget = $this->findForMonth($householdId, $periodMonth);
        $items = $budget !== null ? $this->listItemsForBudget((int) $budget['id']) : [];
        $expenseItems = array_values(array_filter($items, fn (array $i): bool => $i['category_type'] === 'expense'));

        if ($expenseItems === []) {
            return ['planned' => '0.00', 'spent' => '0.00', 'remaining' => '0.00', 'has_items' => false];
        }

        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));
        $actualByCategory = $this->actualByCategory($householdId, 'expense', $periodMonth, $monthEnd);

        $planned = '0.00';
        $spent = '0.00';
        foreach ($expenseItems as $item) {
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
