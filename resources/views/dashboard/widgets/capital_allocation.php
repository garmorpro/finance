<?php

/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$hasActivity = $allocationSummary !== null && bccomp($allocationSummary['income'], '0.00', 2) > 0;

$pctOf = static function (string $amount, string $income): float {
    if (bccomp($income, '0.00', 2) <= 0) {
        return 0.0;
    }

    return max(0.0, (float) bcdiv(bcmul($amount, '100', 4), $income, 4));
};

$slices = [];
if ($hasActivity) {
    $slices = [
        ['label' => 'Lifestyle', 'color' => '#c94f32', 'pct' => $pctOf($allocationSummary['livingExpenses'], $allocationSummary['income'])],
        ['label' => 'Savings', 'color' => '#059669', 'pct' => $pctOf($allocationSummary['savings'], $allocationSummary['income'])],
        ['label' => 'Debt', 'color' => '#a53d27', 'pct' => $pctOf($allocationSummary['debtPayments'], $allocationSummary['income'])],
        ['label' => 'Giving', 'color' => '#a8a29e', 'pct' => $pctOf($allocationSummary['giving'], $allocationSummary['income'])],
        ['label' => 'Remaining', 'color' => '#15803d', 'pct' => $pctOf($allocationSummary['remaining'], $allocationSummary['income'])],
    ];

    $stops = [];
    $cumulative = 0.0;
    foreach ($slices as $slice) {
        $stops[] = sprintf('%s %s%% %s%%', $slice['color'], $cumulative, $cumulative + $slice['pct']);
        $cumulative += $slice['pct'];
    }
    $gradient = 'conic-gradient(' . implode(', ', $stops) . ')';
}

View::partial('dashboard/widgets/_header', ['title' => 'Capital Allocation', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Where this month's income actually went.</p>
<?php if (!$hasActivity): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No income recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <div class="flex items-center gap-5">
        <div class="donut" style="background: <?= $gradient ?>;">
            <div class="donut-hole bg-white dark:bg-stone-900">
                <span class="text-lg font-semibold text-stone-900 dark:text-white"><?= Money::formatCompact($allocationSummary['income']) ?></span>
                <span class="text-[10px] text-stone-400 dark:text-stone-500">allocated</span>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <?php foreach ($slices as $slice): ?>
                <div class="flex items-center gap-2">
                    <span class="legend-dot" style="background:<?= $slice['color'] ?>"></span>
                    <span class="text-stone-700 dark:text-stone-300"><?= View::e($slice['label']) ?></span>
                    <span class="ml-auto font-medium text-stone-900 dark:text-white"><?= number_format($slice['pct'], 0) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
