<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds the JSON payload for `GET /api/master-hq/v1/summary` — a
 * read-only, high-level financial summary for an external consumer
 * ("Master HQ") to display, not recalculate. Every number here comes
 * from ReportingService, which is already this app's single place for
 * financial aggregation (per CLAUDE.md's "dedicated calculation or
 * reporting services" rule) — this class only shapes that data into the
 * API's JSON contract, it never computes a financial total itself.
 *
 * MyCFO+ remains the source of truth: this service (and the endpoint
 * behind it) is the *only* sanctioned way to read financial data out of
 * this app for an external system. Master HQ never queries this app's
 * database directly.
 */
final class MasterHqSummaryService
{
    /**
     * No per-household currency setting exists anywhere in this app's
     * schema (households/accounts have none) — every amount is an
     * implicitly-USD DECIMAL(14,2). Hardcoded here rather than invented
     * as a fake per-household setting nothing else in the app has.
     */
    private const CURRENCY = 'USD';

    public function __construct(
        private readonly ReportingService $reporting = new ReportingService()
    ) {
    }

    /**
     * @return array<string, mixed> shaped to match the Master HQ API contract — see docs/api.md
     */
    public function buildSummary(int $householdId): array
    {
        $currentMonthStart = gmdate('Y-m-01');
        $currentMonthNumber = (int) gmdate('n'); // 1 (Jan) .. 12 (Dec)

        // ReportingService::cashFlow()'s monthly buckets are oldest-first,
        // and its latest bucket is inherently "month to date" (the
        // underlying query has no upper date bound — see
        // ReportingService::monthlyIncomeExpense()). Asking for
        // (current month number + 1) months back always reaches back to
        // December of the previous year, which guarantees both a real
        // "previous month" bucket and a complete Jan-through-now window
        // for year-to-date — including the January edge case, where
        // "previous month" is December of the *prior* year.
        $series = $this->reporting->cashFlow($householdId, $currentMonthNumber + 1);

        $currentMonth = $series[count($series) - 1];
        $previousMonth = $series[count($series) - 2];

        // The last $currentMonthNumber entries are exactly Jan..now of
        // this calendar year (the one extra month requested above was
        // only for the "previous month" lookback and isn't part of YTD).
        $ytdMonths = array_slice($series, -$currentMonthNumber);
        $ytdIncome = '0.00';
        $ytdExpenses = '0.00';
        foreach ($ytdMonths as $month) {
            $ytdIncome = bcadd($ytdIncome, $month['income'], 2);
            $ytdExpenses = bcadd($ytdExpenses, $month['expenses'], 2);
        }
        $ytdNet = bcsub($ytdIncome, $ytdExpenses, 2);

        $allocationSummary = $this->reporting->monthlyAllocationSummary($householdId, $currentMonthStart);
        $savingsRate = $this->reporting->savingsRate($allocationSummary);

        // Trend covers the current reporting period (month to date) at
        // daily granularity, ending the day after "today" so today's own
        // transactions are included (date range is [start, end)).
        $trendEnd = date('Y-m-d', strtotime('+1 day'));
        $trend = $this->reporting->dailyIncomeExpense($householdId, $currentMonthStart, $trendEnd);

        return [
            'period' => gmdate('Y-m'),
            'currency' => self::CURRENCY,

            'income' => [
                'monthToDate' => $currentMonth['income'],
                'previousMonth' => $previousMonth['income'],
                'changePercent' => $this->percentChange($currentMonth['income'], $previousMonth['income']),
            ],

            'expenses' => [
                'monthToDate' => $currentMonth['expenses'],
                'previousMonth' => $previousMonth['expenses'],
                'changePercent' => $this->percentChange($currentMonth['expenses'], $previousMonth['expenses']),
            ],

            'net' => [
                'monthToDate' => $currentMonth['net'],
                'yearToDate' => $ytdNet,
                'changePercent' => $this->percentChange($currentMonth['net'], $previousMonth['net']),
            ],

            'savingsRate' => $savingsRate,

            'trend' => array_map(
                fn (array $day): array => [
                    'date' => $day['date'],
                    'income' => $day['income'],
                    'expenses' => $day['expenses'],
                ],
                $trend
            ),

            'incomeSources' => $this->incomeSources($householdId, $currentMonthStart),

            'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * This app has no distinct "income source" entity (a job, a client, a
     * side hustle) — only a flat income-type category (confirmed: no
     * migration or table models hours/employer/hourly-rate anywhere).
     * Categories are the closest real thing to map onto the shape Master
     * HQ asks for, so that's what this returns — `type` is always
     * "other", and `hours`/`effectiveHourlyRate`/`expenses` are always
     * null rather than fabricated, since none of those are tracked per
     * category. See docs/api.md's "known unsupported metrics".
     *
     * @return list<array{id: string, name: string, type: string, income: string, expenses: null, net: string, hours: null, effectiveHourlyRate: null}>
     */
    private function incomeSources(int $householdId, string $periodMonth): array
    {
        $byCategory = $this->reporting->incomeByCategory($householdId, $periodMonth);

        return array_map(
            fn (array $row): array => [
                // A category's own primary key, namespaced rather than a
                // bare integer, so it doesn't read as some other kind of
                // internal record — see docs/api.md.
                'id' => 'cat_' . $row['id'],
                'name' => $row['name'],
                'type' => 'other',
                'income' => $row['amount'],
                'expenses' => null,
                'net' => $row['amount'],
                'hours' => null,
                'effectiveHourlyRate' => null,
            ],
            $byCategory
        );
    }

    /**
     * Null when $previous is zero — an undefined/infinite percent change
     * is more misleading than an explicit "not available" would be.
     */
    private function percentChange(string $current, string $previous): ?float
    {
        if (bccomp($previous, '0.00', 2) === 0) {
            return null;
        }

        $diff = bcsub($current, $previous, 6);

        return (float) bcdiv(bcmul($diff, '100', 6), $previous, 2);
    }
}
