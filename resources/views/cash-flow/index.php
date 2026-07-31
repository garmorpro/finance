<?php

/** @var list<array{label: string, income: string, expenses: string, net: string, cumulative: string}> $series */
/** @var int $months */
/** @var list<int> $monthOptions */
/** @var string $totalIncome */
/** @var string $totalExpenses */
/** @var string $netCashFlow */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$hasActivity = array_sum(array_map(fn (array $r): float => (float) $r['income'] + (float) $r['expenses'], $series)) > 0.0;

$chartPayload = [
    'labels' => array_column($series, 'label'),
    'datasets' => [
        ['label' => 'Net Cash Flow', 'data' => array_map(fn (array $r): float => (float) $r['net'], $series)],
        ['label' => 'Cumulative', 'data' => array_map(fn (array $r): float => (float) $r['cumulative'], $series)],
    ],
];

$netPositive = bccomp($netCashFlow, '0.00', 2) >= 0;
$arrowUpIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cash Flow · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'cash-flow']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Cash Flow</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Money in vs. money out over time. Transfers between your own accounts aren't counted.</p>
                    </div>
                    <form method="GET" action="/cash-flow">
                        <label for="months" class="sr-only">Time range</label>
                        <select id="months" name="months" class="field-input" onchange="this.form.submit()">
                            <?php foreach ($monthOptions as $option): ?>
                                <option value="<?= $option ?>" <?= $option === $months ? 'selected' : '' ?>>Last <?= $option ?> months</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (!$hasActivity): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400 mb-2">No income or expense transactions in this period.</p>
                        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add a transaction &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="stat-hero <?= $netPositive ? 'stat-hero-positive' : 'stat-hero-negative' ?> flex items-center justify-between flex-wrap gap-6">
                        <span class="stat-hero-icon <?= $netPositive ? 'text-emerald-600' : 'text-red-600' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </span>
                        <div style="position: relative;">
                            <div class="text-xs font-bold uppercase tracking-wide <?= $netPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?> mb-2">Net Cash Flow</div>
                            <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($netCashFlow) ?></div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Total income minus total expenses over the last <?= $months ?> months.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="card">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="metric-icon" style="background: rgba(52, 211, 153, .14); color: #059669;"><?= $arrowUpIcon ?></span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Total Income</div>
                                    <div class="text-xs text-stone-500 dark:text-stone-400">Last <?= $months ?> months</div>
                                </div>
                            </div>
                            <div class="text-xl font-semibold text-emerald-700 dark:text-emerald-400"><?= Money::format($totalIncome) ?></div>
                        </div>
                        <div class="card">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="metric-icon" style="background: rgba(226, 105, 75, .14); color: #c94f32;"><?= $arrowDownIcon ?></span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Total Expenses</div>
                                    <div class="text-xs text-stone-500 dark:text-stone-400">Last <?= $months ?> months</div>
                                </div>
                            </div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalExpenses) ?></div>
                        </div>
                    </div>

                    <div class="card">
                        <div style="height: 320px;">
                            <canvas id="chart-cash-flow" data-chart="cashflow" aria-label="Net cash flow and cumulative total by month" role="img"></canvas>
                        </div>
                        <script type="application/json" id="chart-cash-flow-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                    </div>

                    <div class="card">
                        <table class="table-base">
                            <thead>
                                <tr><th>Month</th><th class="text-right">Income</th><th class="text-right">Expenses</th><th class="text-right">Net</th><th class="text-right">Cumulative</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($series) as $row): ?>
                                    <tr>
                                        <td><?= View::e($row['label']) ?></td>
                                        <td class="text-right text-emerald-700 dark:text-emerald-400"><?= Money::format($row['income']) ?></td>
                                        <td class="text-right"><?= Money::format($row['expenses']) ?></td>
                                        <td class="text-right font-medium <?= bccomp($row['net'], '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($row['net']) ?></td>
                                        <td class="text-right text-stone-500 dark:text-stone-400"><?= Money::format($row['cumulative']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('/assets/js/vendor/chart.umd.min.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/charts.js') ?>" defer></script>
</body>
</html>
