<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;

/**
 * Chart/report data for the dashboard. Kept out of controllers per
 * CLAUDE.md's "dedicated calculation or reporting services" guidance —
 * these queries do real financial aggregation (net worth history, income
 * vs. spending), not just a single repository's CRUD.
 */
final class ReportingService
{
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
     * Income and expense totals per month for the last $months months.
     * Transfers are never income or spending (per CLAUDE.md) and are
     * excluded by the transaction_type filter alone, without needing a
     * separate check. Honors exclude_from_reports, distinct from the
     * exclude_from_budget flag budgets use.
     *
     * @return list<array{label: string, income: string, expenses: string}>
     */
    public function incomeVsSpending(int $householdId, int $months = 6): array
    {
        return $this->monthlyIncomeExpense($householdId, $months);
    }

    /**
     * Same monthly income/expense series as incomeVsSpending(), with a net
     * (income minus expenses) and a running cumulative total added — the
     * "money in vs. money out over time" story a fixed side-by-side
     * comparison doesn't tell on its own.
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
            'SELECT transaction_date, transaction_type, amount FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND exclude_from_reports = 0
               AND transaction_type IN ("income", "expense")
               AND transaction_date >= :start_month'
        );
        $stmt->execute(['household_id' => $householdId, 'start_month' => $startMonth]);

        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $month = substr($row['transaction_date'], 0, 7);
            $byMonth[$month]['income'] ??= '0.00';
            $byMonth[$month]['expenses'] ??= '0.00';

            if ($row['transaction_type'] === 'income') {
                $byMonth[$month]['income'] = bcadd($byMonth[$month]['income'], $row['amount'], 2);
            } else {
                $byMonth[$month]['expenses'] = bcadd($byMonth[$month]['expenses'], bcmul($row['amount'], '-1', 2), 2);
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
     * Expense totals by category for one month, descending by amount.
     *
     * @return list<array{name: string, color: string|null, amount: string}>
     */
    public function spendingByCategory(int $householdId, string $periodMonth): array
    {
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $stmt = Connection::get()->prepare(
            'SELECT c.id AS category_id, c.name, c.color, t.amount
             FROM transactions t
             INNER JOIN categories c ON c.id = t.category_id
             WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.exclude_from_reports = 0
               AND t.transaction_type = "expense"
               AND t.transaction_date >= :month_start AND t.transaction_date < :month_end'
        );
        $stmt->execute(['household_id' => $householdId, 'month_start' => $periodMonth, 'month_end' => $monthEnd]);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            if (!isset($totals[$categoryId])) {
                $totals[$categoryId] = ['name' => $row['name'], 'color' => $row['color'], 'amount' => '0.00'];
            }
            $totals[$categoryId]['amount'] = bcadd($totals[$categoryId]['amount'], bcmul($row['amount'], '-1', 2), 2);
        }

        $totals = array_values($totals);
        usort($totals, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));

        return $totals;
    }

    /**
     * The configurable report behind /reports: an arbitrary date range,
     * optional account/category/type/member filters, and a choice of
     * grouping dimension. Transfers are included in the raw dataset when
     * selected but always kept out of the income/expense net, per
     * CLAUDE.md's rule that transfers are never household income or
     * spending — they get their own column instead.
     *
     * @param array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, type: string, user_id: int|null} $filters
     * @return array{rows: list<array{label: string, income: string, expense: string, transfer: string, net: string, count: int}>, totals: array{income: string, expense: string, transfer: string, net: string, count: int}}
     */
    public function transactionsReport(int $householdId, array $filters, string $groupBy): array
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
            "SELECT t.transaction_type, t.amount, t.transaction_date, t.payee, t.category_id, t.account_id, t.created_by_user_id,
                    c.name AS category_name, a.name AS account_name, u.name AS user_name
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             INNER JOIN accounts a ON a.id = t.account_id
             INNER JOIN users u ON u.id = t.created_by_user_id
             {$where}
             ORDER BY t.transaction_date"
        );
        $stmt->execute($params);

        return $this->groupTransactions($stmt->fetchAll(), $groupBy);
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
    private function groupTransactions(array $transactions, string $groupBy): array
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

        if ($groupBy === 'month') {
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
}
