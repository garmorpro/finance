<?php

/** @var array|null $netWorth */
/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$netWorthChange = null;
if (count($netWorthTrend) >= 2) {
    $netWorthChange = bcsub($netWorthTrend[count($netWorthTrend) - 1]['netWorth'], $netWorthTrend[count($netWorthTrend) - 2]['netWorth'], 2);
}

View::partial('dashboard/widgets/_header', ['title' => 'Net Worth', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $netWorth === null ? '—' : Money::format($netWorth['net']) ?></div>
<?php if ($netWorthChange !== null): ?>
    <p class="text-xs font-medium mt-1 <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
        <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? '+' : '' ?><?= Money::format($netWorthChange) ?> this month
    </p>
<?php else: ?>
    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Not enough history yet</p>
<?php endif; ?>
