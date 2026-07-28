<?php

/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var float|null $runwayMonths */
/** @var array{label: string, income: string, expenses: string, net: string, cumulative: string}|null $thisMonthCashFlow */
/** @var array|null $netWorth */
/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$savingsRate = null;
if ($allocationSummary !== null && bccomp($allocationSummary['income'], '0.00', 2) > 0) {
    $savingsRate = (float) bcdiv(bcmul($allocationSummary['savings'], '100', 4), $allocationSummary['income'], 1);
}

$netWorthChange = null;
if (count($netWorthTrend) >= 2) {
    $netWorthChange = bcsub($netWorthTrend[count($netWorthTrend) - 1]['netWorth'], $netWorthTrend[count($netWorthTrend) - 2]['netWorth'], 2);
}

View::partial('dashboard/widgets/_header', ['title' => 'Key Metrics', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div>
        <div class="text-xs font-medium uppercase tracking-wide text-stone-400 dark:text-stone-500 mb-2">Runway</div>
        <div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $runwayMonths === null ? '—' : number_format($runwayMonths, 1) . ' months' ?></div>
        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">How long savings cover your baseline spending</p>
    </div>
    <div>
        <div class="text-xs font-medium uppercase tracking-wide text-stone-400 dark:text-stone-500 mb-2">Savings Rate</div>
        <div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $savingsRate === null ? '—' : number_format($savingsRate, 0) . '%' ?></div>
        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Of income directed to savings &amp; investing</p>
    </div>
    <div>
        <div class="text-xs font-medium uppercase tracking-wide text-stone-400 dark:text-stone-500 mb-2">Net Worth</div>
        <div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $netWorth === null ? '—' : Money::format($netWorth['net']) ?></div>
        <?php if ($netWorthChange !== null): ?>
            <p class="text-xs font-medium mt-1 <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
                <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? '+' : '' ?><?= Money::format($netWorthChange) ?> this month
            </p>
        <?php else: ?>
            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Not enough history yet</p>
        <?php endif; ?>
    </div>
    <div>
        <div class="text-xs font-medium uppercase tracking-wide text-stone-400 dark:text-stone-500 mb-2">Cash Flow This Month</div>
        <?php if ($thisMonthCashFlow === null): ?>
            <div class="text-2xl font-semibold text-stone-900 dark:text-white">—</div>
        <?php else: ?>
            <div class="text-2xl font-semibold text-stone-900 dark:text-white">
                <?= bccomp($thisMonthCashFlow['net'], '0.00', 2) >= 0 ? '+' : '' ?><?= Money::format($thisMonthCashFlow['net']) ?>
            </div>
            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1"><?= Money::format($thisMonthCashFlow['income']) ?> in &middot; <?= Money::format($thisMonthCashFlow['expenses']) ?> out</p>
        <?php endif; ?>
    </div>
</div>
