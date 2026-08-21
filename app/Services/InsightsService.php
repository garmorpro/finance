<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Support\Money;

/**
 * Assembles the dashboard's "Insights" card: a short, priority-ordered
 * list of plain-language observations, not a new financial calculation
 * of its own. Every number here comes from ReportingService or
 * TransactionRepository — this class only decides which observations
 * are worth surfacing and how to phrase them, the same division of
 * labor MasterHqSummaryService uses for the Master HQ API.
 *
 * Each candidate rule is evaluated in priority order (actionable items
 * first, context after) and skipped when it has nothing true to say.
 * A household with almost no data yet still gets a graceful fallback
 * rather than an empty-looking card — see the "richer with more data"
 * filler at the end of forHousehold().
 *
 * @phpstan-type Insight array{type: 'warn'|'positive'|'info', title: string, subtitle: string, link: array{label: string, href: string}|null}
 */
final class InsightsService
{
    private const MAX_INSIGHTS = 4;

    public function __construct(
        private readonly ReportingService $reporting = new ReportingService(),
        private readonly TransactionRepository $transactions = new TransactionRepository()
    ) {}

    /**
     * @return list<array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}>
     */
    public function forHousehold(int $householdId): array
    {
        $monthStart = gmdate('Y-m-01');
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'));
        $prevMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
        $prevMonthLabel = date('F', strtotime($prevMonthStart));

        $insights = [];

        $categorization = $this->categorizationInsight($householdId, $monthStart, $monthEnd);
        if ($categorization !== null) {
            $insights[] = $categorization;
        }

        $insights[] = $this->biggestCategoryInsight($householdId, $monthStart);

        $spendingTrend = $this->spendingTrendInsight($householdId, $prevMonthLabel);
        if ($spendingTrend !== null) {
            $insights[] = $spendingTrend;
        }

        $savingsRate = $this->savingsRateInsight($householdId, $monthStart, $prevMonthStart, $prevMonthLabel);
        if ($savingsRate !== null) {
            $insights[] = $savingsRate;
        }

        // A brand-new or very lightly used household won't trigger most
        // of the rules above — rather than let the card look sparse or
        // empty, say so honestly instead of fabricating a stat.
        if (count($insights) < 3) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Insights get richer with more data',
                'subtitle' => 'Log a few more transactions and real trends will start surfacing here.',
                'link' => null,
            ];
        }

        return array_slice($insights, 0, self::MAX_INSIGHTS);
    }

    /**
     * @return array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}|null
     */
    private function categorizationInsight(int $householdId, string $monthStart, string $monthEnd): ?array
    {
        $uncategorized = $this->transactions->countUncategorized($householdId, $monthStart, $monthEnd);

        if ($uncategorized > 0) {
            return [
                'type' => 'warn',
                'title' => $uncategorized === 1
                    ? '1 transaction needs a category'
                    : "{$uncategorized} transactions need a category",
                'subtitle' => 'Categorize them so your spending totals stay accurate.',
                'link' => ['label' => 'Review', 'href' => '/transactions'],
            ];
        }

        $sums = $this->transactions->sumsForHousehold($householdId, ['date_from' => $monthStart, 'date_to' => $monthEnd]);
        $totalThisMonth = $sums['incomeCount'] + $sums['expenseCount'];

        if ($totalThisMonth > 0) {
            return [
                'type' => 'positive',
                'title' => 'All transactions are categorized',
                'subtitle' => 'Nothing needs your attention here.',
                'link' => null,
            ];
        }

        return null;
    }

    /**
     * @return array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}
     */
    private function biggestCategoryInsight(int $householdId, string $monthStart): array
    {
        $topExpenses = $this->reporting->spendingByCategory($householdId, $monthStart);

        if ($topExpenses === []) {
            return [
                'type' => 'info',
                'title' => 'No spending recorded yet this month',
                'subtitle' => "Add a transaction to see where it's going.",
                'link' => ['label' => 'Add a transaction', 'href' => '/transactions/create'],
            ];
        }

        $top = $topExpenses[0];
        $total = '0.00';
        foreach ($topExpenses as $row) {
            $total = bcadd($total, $row['amount'], 2);
        }
        $pct = bccomp($total, '0.00', 2) > 0 ? (float) bcdiv(bcmul($top['amount'], '100', 4), $total, 4) : 0.0;

        return [
            'type' => 'info',
            'title' => $top['name'] . ' is your biggest category this month',
            'subtitle' => Money::format($top['amount']) . ' — about ' . number_format($pct, 0) . '% of total spending.',
            'link' => null,
        ];
    }

    /**
     * @return array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}|null
     */
    private function spendingTrendInsight(int $householdId, string $prevMonthLabel): ?array
    {
        // Two-month window: the same trick MasterHqSummaryService uses —
        // cashFlow()'s most recent bucket is always the current month,
        // whatever today's date is, so this needs no separate query.
        $series = $this->reporting->cashFlow($householdId, 2);
        $current = $series[count($series) - 1] ?? null;
        $previous = count($series) >= 2 ? $series[count($series) - 2] : null;

        if ($current === null || $previous === null) {
            return null;
        }
        if (bccomp($previous['expenses'], '0.00', 2) <= 0 || bccomp($current['expenses'], '0.00', 2) <= 0) {
            return null;
        }

        $changePct = (float) bcdiv(bcmul(bcsub($current['expenses'], $previous['expenses'], 4), '100', 4), $previous['expenses'], 1);
        $up = $changePct >= 0;

        return [
            // More spending isn't automatically bad (a big one-off
            // purchase, say), so this stays neutral rather than red;
            // less spending gets the positive/green treatment.
            'type' => $up ? 'info' : 'positive',
            'title' => 'Spending is ' . ($up ? 'up' : 'down') . ' ' . number_format(abs($changePct), 0) . '% vs last month',
            'subtitle' => Money::format($current['expenses']) . ' this month, compared with ' . Money::format($previous['expenses']) . " in {$prevMonthLabel}.",
            'link' => null,
        ];
    }

    /**
     * @return array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}|null
     */
    private function savingsRateInsight(int $householdId, string $monthStart, string $prevMonthStart, string $prevMonthLabel): ?array
    {
        $allocationSummary = $this->reporting->monthlyAllocationSummary($householdId, $monthStart);
        $savingsRate = $this->reporting->savingsRate($allocationSummary);

        // A 0% rate isn't wrong, just not worth an "insight" — it's
        // usually because goal contributions haven't been logged yet,
        // not a meaningful trend to report on.
        if ($savingsRate === null || $savingsRate <= 0) {
            return null;
        }

        $prevAllocationSummary = $this->reporting->monthlyAllocationSummary($householdId, $prevMonthStart);
        $prevSavingsRate = $this->reporting->savingsRate($prevAllocationSummary);

        if ($prevSavingsRate === null) {
            return [
                'type' => 'positive',
                'title' => 'Savings rate is ' . number_format($savingsRate, 0) . '% this month',
                'subtitle' => 'Of income directed to savings and investing.',
                'link' => null,
            ];
        }

        $up = $savingsRate >= $prevSavingsRate;

        return [
            'type' => $up ? 'positive' : 'info',
            'title' => 'Savings rate is ' . ($up ? 'up' : 'down') . ' vs last month',
            'subtitle' => number_format($savingsRate, 0) . '% this month vs ' . number_format($prevSavingsRate, 0) . "% in {$prevMonthLabel}.",
            'link' => null,
        ];
    }
}
