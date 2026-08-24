<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\GoalRepository;
use App\Support\FieldCipher;

/**
 * Chart/report data for the dashboard. Kept out of controllers per
 * CLAUDE.md's "dedicated calculation or reporting services" guidance —
 * these queries do real financial aggregation (net worth history, income
 * vs. spending), not just a single repository's CRUD.
 */
final class ReportingService
{
    /**
     * Whitelist of columns the Reports table may be sorted by — validated
     * against this before ever reaching a comparator, same reasoning as
     * TransactionRepository::SORTABLE_COLUMNS (never trust a client-supplied
     * sort key directly).
     */
    public const REPORT_SORTABLE_FIELDS = ['label', 'income', 'expense', 'transfer', 'net', 'count'];

    /**
     * Month-end net worth for each of the last $months months, built from
     * account_balance_history rather than transactions (per CLAUDE.md:
     * "Do not calculate net worth solely from transactions"). Only
     * currently-active accounts with include_in_net_worth = 1 are
     * included — an account archived partway through the window drops
     * out of every month's figure, not just the months after it was
     * archived. That's a known simplification: it keeps the chart
     * consistent with the dashboard's current net worth figure at the
     * rightmost point, at the cost of not reflecting a since-archived
     * account's historical contribution.
     *
     * @return list<array{label: string, assets: string, liabilities: string, netWorth: string}>
     */
    public function netWorthTrend(int $householdId, int $months = 12): array
    {
        $accounts = array_values(array_filter(
            (new AccountRepository())->listForHousehold($householdId),
            fn (array $a): bool => (int) $a['include_in_net_worth'] === 1
        ));

        if ($accounts === []) {
            return [];
        }

        $historyByAccount = [];
        foreach ((new AccountBalanceHistoryRepository())->listForAccounts(array_map(fn (array $a): int => (int) $a['id'], $accounts)) as $row) {
            $historyByAccount[(int) $row['account_id']][] = $row;
        }

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
            $cutoff = $monthEnd . ' 23:59:59';

            $assets = '0.00';
            $liabilities = '0.00';

            foreach ($accounts as $account) {
                if ($account['created_at'] > $cutoff) {
                    continue;
                }

                $balance = $this->balanceAsOf($account, $historyByAccount[(int) $account['id']] ?? [], $cutoff);

                if (AccountRepository::isLiability($account['account_type'])) {
                    $liabilities = bcadd($liabilities, $balance, 2);
                } else {
                    $assets = bcadd($assets, $balance, 2);
                }
            }

            $series[] = [
                'label' => date('M Y', strtotime($monthEnd)),
                'assets' => $assets,
                'liabilities' => $liabilities,
                'netWorth' => bcsub($assets, $liabilities, 2),
            ];
        }

        return $series;
    }

    /**
     * @param array $history oldest-first rows for one account
     */
    private function balanceAsOf(array $account, array $history, string $cutoff): string
    {
        $latest = null;
        foreach ($history as $row) {
            if ($row['created_at'] > $cutoff) {
                break;
            }
            $latest = $row['new_balance'];
        }

        if ($latest !== null) {
            return $latest;
        }

        // No history at/before the cutoff. If there's history after it,
        // the balance at the cutoff was whatever it was right before that
        // first recorded change. Otherwise it's never changed since the
        // account was created.
        return $history !== [] ? $history[0]['previous_balance'] : $account['current_balance'];
    }

    /**
     * Monthly income/expense series with a net (income minus expenses) and
     * a running cumulative total added — the "money in vs. money out over
     * time" story. Transfers are never income or spending (per CLAUDE.md)
     * and are excluded by the transaction_type filter alone. Honors
     * exclude_from_reports, distinct from the exclude_from_budget flag
     * budgets use.
     *
     * @return list<array{label: string, income: string, expenses: string, net: string, cumulative: string}>
     */
    public function cashFlow(int $householdId, int $months = 12): array
    {
        $series = $this->monthlyIncomeExpense($householdId, $months);

        $cumulative = '0.00';
        foreach ($series as &$row) {
            $net = bcsub($row['income'], $row['expenses'], 2);
            $cumulative = bcadd($cumulative, $net, 2);
            $row['net'] = $net;
            $row['cumulative'] = $cumulative;
        }
        unset($row);

        return $series;
    }

    /**
     * @return list<array{label: string, income: string, expenses: string}>
     */
    private function monthlyIncomeExpense(int $householdId, int $months): array
    {
        $startMonth = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $stmt = Connection::get()->prepare(
            'SELECT transaction_date, transaction_type, amount, amount_encrypted FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND exclude_from_reports = 0
               AND transaction_type IN ("income", "expense")
               AND transaction_date >= :start_month'
        );
        $stmt->execute(['household_id' => $householdId, 'start_month' => $startMonth]);

        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $month = substr($row['transaction_date'], 0, 7);
            $byMonth[$month]['income'] ??= '0.00';
            $byMonth[$month]['expenses'] ??= '0.00';

            if ($row['transaction_type'] === 'income') {
                $byMonth[$month]['income'] = bcadd($byMonth[$month]['income'], $amount, 2);
            } else {
                $byMonth[$month]['expenses'] = bcadd($byMonth[$month]['expenses'], bcmul($amount, '-1', 2), 2);
            }
        }

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthKey = date('Y-m', strtotime("-{$i} months"));
            $series[] = [
                'label' => date('M Y', strtotime($monthKey . '-01')),
                'income' => $byMonth[$monthKey]['income'] ?? '0.00',
                'expenses' => $byMonth[$monthKey]['expenses'] ?? '0.00',
            ];
        }

        return $series;
    }

    /**
     * Daily income/expense series for a date range — every other trend
     * series in this class (`cashFlow()`, `netWorthTrend()`) is monthly;
     * this is the first daily granularity, added for the Master HQ
     * summary API's chart data rather than any page in this app itself.
     * Same conventions as `monthlyIncomeExpense()`: transfers excluded
     * via the transaction_type filter, exclude_from_reports honored,
     * expenses returned as a positive magnitude. Zero-filled for days
     * with no activity, oldest first.
     *
     * @return list<array{date: string, income: string, expenses: string}>
     */
    public function dailyIncomeExpense(int $householdId, string $startDate, string $endDateExclusive): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT transaction_date, transaction_type, amount, amount_encrypted FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND exclude_from_reports = 0
               AND transaction_type IN ("income", "expense")
               AND transaction_date >= :start_date AND transaction_date < :end_date'
        );
        $stmt->execute(['household_id' => $householdId, 'start_date' => $startDate, 'end_date' => $endDateExclusive]);

        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $date = $row['transaction_date'];
            $byDate[$date]['income'] ??= '0.00';
            $byDate[$date]['expenses'] ??= '0.00';

            if ($row['transaction_type'] === 'income') {
                $byDate[$date]['income'] = bcadd($byDate[$date]['income'], $amount, 2);
            } else {
                $byDate[$date]['expenses'] = bcadd($byDate[$date]['expenses'], bcmul($amount, '-1', 2), 2);
            }
        }

        $dayCount = (int) ((strtotime($endDateExclusive) - strtotime($startDate)) / 86400);
        $dayCount = max(0, $dayCount);

        $series = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $dateKey = date('Y-m-d', strtotime($startDate . " +{$i} days"));
            $series[] = [
                'date' => $dateKey,
                'income' => $byDate[$dateKey]['income'] ?? '0.00',
                'expenses' => $byDate[$dateKey]['expenses'] ?? '0.00',
            ];
        }

        return $series;
    }

    /**
     * Expense totals by category for one month, descending by amount.
     * Split lines are read individually rather than the parent
     * transaction's row — see BudgetRepository::actualByCategory()'s
     * doc comment for why (the parent's category_id on a split
     * transaction is only a "primary" display category).
     *
     * @return list<array{name: string, color: string|null, amount: string}>
     */
    public function spendingByCategory(int $householdId, string $periodMonth): array
    {
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $stmt = Connection::get()->prepare(
            'SELECT category_id, amount, amount_encrypted FROM (
                SELECT t.category_id AS category_id, t.amount AS amount, t.amount_encrypted AS amount_encrypted
                FROM transactions t
                WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.exclude_from_reports = 0
                  AND t.transaction_type = "expense" AND t.is_split = 0
                  AND t.transaction_date >= :month_start AND t.transaction_date < :month_end
                UNION ALL
                SELECT ts.category_id AS category_id, ts.amount AS amount, ts.amount_encrypted AS amount_encrypted
                FROM transactions t
                INNER JOIN transaction_splits ts ON ts.transaction_id = t.id
                WHERE t.household_id = :household_id2 AND t.deleted_at IS NULL AND t.exclude_from_reports = 0
                  AND t.transaction_type = "expense" AND t.is_split = 1
                  AND t.transaction_date >= :month_start2 AND t.transaction_date < :month_end2
            ) combined
            WHERE category_id IS NOT NULL'
        );
        $stmt->execute([
            'household_id' => $householdId,
            'month_start' => $periodMonth,
            'month_end' => $monthEnd,
            'household_id2' => $householdId,
            'month_start2' => $periodMonth,
            'month_end2' => $monthEnd,
        ]);

        $categoryRows = $stmt->fetchAll();
        if ($categoryRows === []) {
            return [];
        }

        $categories = (new CategoryRepository())->listForHousehold($householdId, true);
        $categoryById = [];
        foreach ($categories as $category) {
            $categoryById[(int) $category['id']] = $category;
        }

        $totals = [];
        foreach ($categoryRows as $row) {
            $categoryId = (int) $row['category_id'];
            if (!isset($categoryById[$categoryId])) {
                continue;
            }
            if (!isset($totals[$categoryId])) {
                $totals[$categoryId] = ['name' => $categoryById[$categoryId]['name'], 'color' => $categoryById[$categoryId]['color'], 'amount' => '0.00'];
            }
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $totals[$categoryId]['amount'] = bcadd($totals[$categoryId]['amount'], bcmul($amount, '-1', 2), 2);
        }

        $totals = array_values($totals);
        usort($totals, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));

        return $totals;
    }

    /**
     * Same shape as spendingByCategory(), for the "Income by Source"
     * dashboard widget — income amounts are already stored positive (no
     * sign flip needed, unlike expense amounts).
     *
     * @return list<array{id: int, name: string, color: string|null, amount: string}>
     */
    public function incomeByCategory(int $householdId, string $periodMonth): array
    {
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $stmt = Connection::get()->prepare(
            'SELECT category_id, amount, amount_encrypted FROM (
                SELECT t.category_id AS category_id, t.amount AS amount, t.amount_encrypted AS amount_encrypted
                FROM transactions t
                WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.exclude_from_reports = 0
                  AND t.transaction_type = "income" AND t.is_split = 0
                  AND t.transaction_date >= :month_start AND t.transaction_date < :month_end
                UNION ALL
                SELECT ts.category_id AS category_id, ts.amount AS amount, ts.amount_encrypted AS amount_encrypted
                FROM transactions t
                INNER JOIN transaction_splits ts ON ts.transaction_id = t.id
                WHERE t.household_id = :household_id2 AND t.deleted_at IS NULL AND t.exclude_from_reports = 0
                  AND t.transaction_type = "income" AND t.is_split = 1
                  AND t.transaction_date >= :month_start2 AND t.transaction_date < :month_end2
            ) combined
            WHERE category_id IS NOT NULL'
        );
        $stmt->execute([
            'household_id' => $householdId,
            'month_start' => $periodMonth,
            'month_end' => $monthEnd,
            'household_id2' => $householdId,
            'month_start2' => $periodMonth,
            'month_end2' => $monthEnd,
        ]);

        $categoryRows = $stmt->fetchAll();
        if ($categoryRows === []) {
            return [];
        }

        $categories = (new CategoryRepository())->listForHousehold($householdId, true);
        $categoryById = [];
        foreach ($categories as $category) {
            $categoryById[(int) $category['id']] = $category;
        }

        $totals = [];
        foreach ($categoryRows as $row) {
            $categoryId = (int) $row['category_id'];
            if (!isset($categoryById[$categoryId])) {
                continue;
            }
            if (!isset($totals[$categoryId])) {
                $totals[$categoryId] = ['id' => $categoryId, 'name' => $categoryById[$categoryId]['name'], 'color' => $categoryById[$categoryId]['color'], 'amount' => '0.00'];
            }
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $totals[$categoryId]['amount'] = bcadd($totals[$categoryId]['amount'], $amount, 2);
        }

        $totals = array_values($totals);
        usort($totals, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));

        return $totals;
    }

    /**
     * Splits a month's money into buckets for the "Lifecycle of a Dollar"
     * and "Capital Allocation" dashboard widgets. Living Expenses
     * deliberately excludes whatever Giving accounts for (so it isn't
     * counted twice); Debt Payments and Savings never overlap with
     * expense transactions at all — a debt payment is a transfer (never
     * typed as an "expense"), and a goal contribution lives in its own
     * ledger table, not the transactions table. Giving is the one
     * heuristic here: spending in a category literally named "Giving" or
     * containing "gift", since there's no dedicated giving ledger.
     *
     * @return array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}
     */
    public function monthlyAllocationSummary(int $householdId, string $periodMonth): array
    {
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $stmt = Connection::get()->prepare(
            'SELECT transaction_type, amount, amount_encrypted FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND exclude_from_reports = 0
               AND transaction_type IN ("income", "expense")
               AND transaction_date >= :month_start AND transaction_date < :month_end'
        );
        $stmt->execute(['household_id' => $householdId, 'month_start' => $periodMonth, 'month_end' => $monthEnd]);

        $income = '0.00';
        $totalExpenses = '0.00';
        foreach ($stmt->fetchAll() as $row) {
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            if ($row['transaction_type'] === 'income') {
                $income = bcadd($income, $amount, 2);
            } else {
                $totalExpenses = bcadd($totalExpenses, bcmul($amount, '-1', 2), 2);
            }
        }

        $giving = '0.00';
        foreach ($this->spendingByCategory($householdId, $periodMonth) as $category) {
            if (preg_match('/giving|gift/i', $category['name']) === 1) {
                $giving = bcadd($giving, $category['amount'], 2);
            }
        }

        $debtPayments = $this->debtPaymentsForMonth($householdId, $periodMonth, $monthEnd);
        $savings = (new GoalRepository())->totalContributionsForRange($householdId, $periodMonth, $monthEnd);
        $livingExpenses = bcsub($totalExpenses, $giving, 2);

        $remaining = bcsub($income, $livingExpenses, 2);
        $remaining = bcsub($remaining, $debtPayments, 2);
        $remaining = bcsub($remaining, $savings, 2);
        $remaining = bcsub($remaining, $giving, 2);

        return [
            'income' => $income,
            'livingExpenses' => $livingExpenses,
            'debtPayments' => $debtPayments,
            'savings' => $savings,
            'giving' => $giving,
            'remaining' => $remaining,
        ];
    }

    /**
     * Percentage of income directed to savings/investing this month —
     * the dashboard hero's "Savings Rate" stat (see
     * dashboard/widgets/hero_stats.php) and the Master HQ summary API's
     * `savingsRate` field. Extracted from what was originally inline
     * logic in one of those callers so the other doesn't have to
     * re-derive the same formula. "Savings"
     * here is exactly what `monthlyAllocationSummary()['savings']` is:
     * logged `goal_contributions` for the period, not a category-based
     * guess. Null when there's no income to divide by, rather than a
     * misleading 0%/infinite figure.
     */
    public function savingsRate(?array $allocationSummary): ?float
    {
        if ($allocationSummary === null || bccomp($allocationSummary['income'], '0.00', 2) <= 0) {
            return null;
        }

        return (float) bcdiv(bcmul($allocationSummary['savings'], '100', 4), $allocationSummary['income'], 1);
    }

    /**
     * This month's transfers into a liability account — a real credit
     * card or loan payment, read from the same transfer mechanism
     * `TransactionController::storeTransfer()` writes (the destination
     * side of a transfer always has a positive amount), not a
     * category-name guess.
     */
    private function debtPaymentsForMonth(int $householdId, string $monthStart, string $monthEndExclusive): string
    {
        // amount is encrypted (see App\Support\FieldCipher), so the
        // "t.amount > 0" filter can no longer run in SQL — every transfer
        // in the month is fetched instead, and the positive-amount check
        // (the destination side of a transfer pair) happens in PHP after
        // decrypting, alongside the existing isLiability() check.
        $stmt = Connection::get()->prepare(
            'SELECT t.amount AS amount, t.amount_encrypted AS amount_encrypted, a.account_type AS account_type
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.household_id = :household_id AND t.deleted_at IS NULL
               AND t.transaction_type = "transfer"
               AND t.transaction_date >= :month_start AND t.transaction_date < :month_end'
        );
        $stmt->execute(['household_id' => $householdId, 'month_start' => $monthStart, 'month_end' => $monthEndExclusive]);

        $total = '0.00';
        foreach ($stmt->fetchAll() as $row) {
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);

            if (bccomp($amount, '0.00', 2) > 0 && AccountRepository::isLiability($row['account_type'])) {
                $total = bcadd($total, $amount, 2);
            }
        }

        return $total;
    }

    /**
     * A rough "how many months could liquid savings cover normal
     * spending" estimate — liquid balance (checking/savings/cash
     * accounts counted toward net worth) divided by the trailing
     * 3-month average expense. Clearly an estimate, not a forecast:
     * returns null when there's no expense history to divide by, rather
     * than a misleading infinite/zero runway.
     */
    public function runwayMonths(int $householdId): ?float
    {
        $liquidTypes = ['checking', 'savings', 'cash'];
        $liquid = '0.00';
        foreach ((new AccountRepository())->listForHousehold($householdId) as $account) {
            if ((int) $account['include_in_net_worth'] === 1 && in_array($account['account_type'], $liquidTypes, true)) {
                $liquid = bcadd($liquid, $account['current_balance'], 2);
            }
        }

        $totalExpense = '0.00';
        foreach ($this->monthlyIncomeExpense($householdId, 3) as $month) {
            $totalExpense = bcadd($totalExpense, $month['expenses'], 2);
        }
        $avgExpense = bcdiv($totalExpense, '3', 2);

        if (bccomp($avgExpense, '0.00', 2) <= 0) {
            return null;
        }

        return (float) bcdiv($liquid, $avgExpense, 4);
    }

    /**
     * The configurable report behind /reports: an arbitrary date range,
     * optional account/category/type/member filters, and a choice of
     * grouping dimension. Transfers are included in the raw dataset when
     * selected but always kept out of the income/expense net, per
     * CLAUDE.md's rule that transfers are never household income or
     * spending — they get their own column instead.
     *
     * @param array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, tag_ids: list<int>, type: string, user_id: int|null} $filters
     * @return array{rows: list<array{label: string, income: string, expense: string, transfer: string, net: string, count: int}>, totals: array{income: string, expense: string, transfer: string, net: string, count: int}}
     */
    public function transactionsReport(int $householdId, array $filters, string $groupBy, ?string $sort = null, string $dir = 'desc'): array
    {
        $clauses = [
            't.household_id = :household_id',
            't.deleted_at IS NULL',
            't.exclude_from_reports = 0',
            't.transaction_date >= :date_from',
            't.transaction_date <= :date_to',
        ];
        $params = [
            'household_id' => $householdId,
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        if ($filters['account_ids'] !== []) {
            [$inClause, $inParams] = $this->buildInClause('account_id', $filters['account_ids']);
            $clauses[] = "t.account_id {$inClause}";
            $params += $inParams;
        }

        if ($filters['category_ids'] !== []) {
            [$inClause, $inParams] = $this->buildInClause('category_id', $filters['category_ids']);
            $clauses[] = "t.category_id {$inClause}";
            $params += $inParams;
        }

        if ($filters['tag_ids'] !== []) {
            [$inClause, $inParams] = $this->buildInClause('tag_id', $filters['tag_ids']);
            $clauses[] = "t.id IN (SELECT tt.transaction_id FROM transaction_tags tt WHERE tt.tag_id {$inClause})";
            $params += $inParams;
        }

        if ($filters['type'] !== '') {
            $clauses[] = 't.transaction_type = :type';
            $params['type'] = $filters['type'];
        }

        if ($filters['user_id'] !== null) {
            $clauses[] = 't.created_by_user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }

        $where = 'WHERE ' . implode(' AND ', $clauses);

        $stmt = Connection::get()->prepare(
            "SELECT t.transaction_type, t.amount, t.amount_encrypted, t.transaction_date, t.payee, t.payee_encrypted,
                    t.category_id, t.account_id, t.created_by_user_id,
                    c.name AS category_name, a.name AS account_name, a.name_encrypted AS account_name_encrypted, u.name AS user_name
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             INNER JOIN accounts a ON a.id = t.account_id
             INNER JOIN users u ON u.id = t.created_by_user_id
             {$where}
             ORDER BY t.transaction_date"
        );
        $stmt->execute($params);

        // account_name comes from a SQL JOIN against accounts.name, which
        // (see AccountRepository) is only ever NULL now when groupBy is
        // "account" — the real value lives encrypted in
        // accounts.name_encrypted, selected above. amount/payee are
        // likewise encrypted on the transactions table itself (see
        // App\Support\FieldCipher) — groupTransactions() below already
        // operates on plain PHP values (bcadd, string grouping keys), so
        // decrypting here is the only change it needs.
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['account_name'] = FieldCipher::decryptOrFallback($row['account_name_encrypted'], $row['account_name']);
            $row['amount'] = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $row['payee'] = FieldCipher::decryptOrFallback($row['payee_encrypted'], $row['payee']);
            unset($row['account_name_encrypted'], $row['amount_encrypted'], $row['payee_encrypted']);
        }
        unset($row);

        return $this->groupTransactions($rows, $groupBy, $sort, $dir);
    }

    /**
     * @param list<int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private function buildInClause(string $paramPrefix, array $ids): array
    {
        $placeholders = [];
        $params = [];

        foreach (array_values($ids) as $i => $id) {
            $key = "{$paramPrefix}_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }

        return ['IN (' . implode(',', $placeholders) . ')', $params];
    }

    /**
     * @param array $transactions
     * @return array{rows: list<array>, totals: array{income: string, expense: string, transfer: string, net: string, count: int}}
     */
    private function groupTransactions(array $transactions, string $groupBy, ?string $sort = null, string $dir = 'desc'): array
    {
        $groups = [];
        $totals = ['income' => '0.00', 'expense' => '0.00', 'transfer' => '0.00', 'count' => 0];

        foreach ($transactions as $t) {
            [$key, $label] = match ($groupBy) {
                'category' => [$t['category_id'] !== null ? 'c' . $t['category_id'] : 'uncategorized', $t['category_name'] ?? 'Uncategorized'],
                'merchant' => ['m:' . $t['payee'], $t['payee']],
                'account' => ['a' . $t['account_id'], $t['account_name']],
                'member' => ['u' . $t['created_by_user_id'], $t['user_name']],
                'month' => [substr($t['transaction_date'], 0, 7), date('M Y', strtotime($t['transaction_date']))],
                default => ['all', 'All'],
            };

            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'income' => '0.00', 'expense' => '0.00', 'transfer' => '0.00', 'count' => 0];
            }

            $groups[$key]['count']++;
            $totals['count']++;

            if ($t['transaction_type'] === 'income') {
                $groups[$key]['income'] = bcadd($groups[$key]['income'], $t['amount'], 2);
                $totals['income'] = bcadd($totals['income'], $t['amount'], 2);
            } elseif ($t['transaction_type'] === 'expense') {
                $expense = bcmul($t['amount'], '-1', 2);
                $groups[$key]['expense'] = bcadd($groups[$key]['expense'], $expense, 2);
                $totals['expense'] = bcadd($totals['expense'], $expense, 2);
            } else {
                $groups[$key]['transfer'] = bcadd($groups[$key]['transfer'], $t['amount'], 2);
                $totals['transfer'] = bcadd($totals['transfer'], $t['amount'], 2);
            }
        }

        foreach ($groups as &$group) {
            $group['net'] = bcsub($group['income'], $group['expense'], 2);
        }
        unset($group);

        // An explicit column sort (user clicked a table header) always wins.
        // Without one, month grouping defaults to chronological order (best
        // for reading a trend) and every other grouping defaults to total
        // activity descending (biggest categories/merchants/etc. first).
        if ($sort !== null && in_array($sort, self::REPORT_SORTABLE_FIELDS, true)) {
            $rows = array_values($groups);
            $this->sortReportRows($rows, $sort, $dir);
        } elseif ($groupBy === 'month') {
            ksort($groups);
            $rows = array_values($groups);
        } else {
            $rows = array_values($groups);
            usort(
                $rows,
                fn (array $a, array $b): int => bccomp(bcadd($b['income'], $b['expense'], 2), bcadd($a['income'], $a['expense'], 2), 2)
            );
        }

        $totals['net'] = bcsub($totals['income'], $totals['expense'], 2);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @param list<array> $rows sorted in place
     */
    private function sortReportRows(array &$rows, string $sort, string $dir): void
    {
        $multiplier = $dir === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($sort, $multiplier): int {
            if ($sort === 'label') {
                return $multiplier * strcasecmp($a['label'], $b['label']);
            }

            if ($sort === 'count') {
                return $multiplier * ($a['count'] <=> $b['count']);
            }

            return $multiplier * bccomp($a[$sort], $b[$sort], 2);
        });
    }
}
