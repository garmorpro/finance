<?php

/** @var list<array{name: string, color: string|null, amount: string}> $incomeByCategory */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$palette = ['#c94f32', '#059669', '#a8a29e', '#e88562', '#78716c', '#f0a488', '#57534e', '#fbe4dc'];

$total = '0.00';
foreach ($incomeByCategory as $row) {
    $total = bcadd($total, $row['amount'], 2);
}

// Always drawn from the curated palette above (matching Capital
// Allocation's fixed colors), rather than each category's own stored
// color — categories are colored for the Categories/Transactions
// pages, but this widget's donut is meant to read as part of the same
// terracotta/emerald dashboard palette as its neighbors.
$slices = [];
if (bccomp($total, '0.00', 2) > 0) {
    foreach ($incomeByCategory as $i => $row) {
        $pct = (float) bcdiv(bcmul($row['amount'], '100', 4), $total, 4);
        $slices[] = [
            'label' => $row['name'],
            'color' => $palette[$i % count($palette)],
            'pct' => $pct,
        ];
    }
}

View::partial('dashboard/widgets/_header', ['title' => 'Income by Source', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Why this month's income happened.</p>
<?php if ($slices === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No income recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <?php
    $stops = [];
    $cumulative = 0.0;
    foreach ($slices as $slice) {
        $stops[] = sprintf('%s %s%% %s%%', $slice['color'], $cumulative, $cumulative + $slice['pct']);
        $cumulative += $slice['pct'];
    }
    $gradient = 'conic-gradient(' . implode(', ', $stops) . ')';
    ?>
    <div class="flex items-center gap-5">
        <div class="donut" style="background: <?= $gradient ?>;">
            <div class="donut-hole bg-white dark:bg-stone-900">
                <span class="text-lg font-semibold text-stone-900 dark:text-white"><?= Money::format($total) ?></span>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <?php foreach ($slices as $slice): ?>
                <div class="flex items-center gap-2">
                    <span class="legend-dot" style="background:<?= View::e($slice['color']) ?>"></span>
                    <span class="text-stone-700 dark:text-stone-300"><?= View::e($slice['label']) ?></span>
                    <span class="ml-auto font-medium text-stone-900 dark:text-white"><?= number_format($slice['pct'], 0) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
