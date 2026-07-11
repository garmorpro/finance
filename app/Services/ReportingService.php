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
}
