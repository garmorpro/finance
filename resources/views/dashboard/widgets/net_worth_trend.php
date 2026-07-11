<?php

/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */

use App\Support\Money;
use App\Support\View;

$chartPayload = [
    'labels' => array_column($netWorthTrend, 'label'),
    'datasets' => [
        ['label' => 'Net Worth', 'data' => array_map(fn (array $r): float => (float) $r['netWorth'], $netWorthTrend)],
    ],
];

?>
<div class="tile-header">
    <span class="label-text mb-0">Net Worth Trend</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($netWorthTrend === [] || array_sum(array_map(fn (array $r): float => (float) $r['assets'] + (float) $r['liabilities'], $netWorthTrend)) === 0.0): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">Not enough account history yet.</p>
        <a href="/accounts" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View accounts &rarr;</a>
    </div>
<?php else: ?>
    <div style="height: 220px;">
        <canvas id="chart-net-worth-trend" data-chart="line" aria-label="Net worth trend over the last 12 months" role="img"></canvas>
    </div>
    <script type="application/json" id="chart-net-worth-trend-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <table class="sr-only">
        <caption>Net worth by month for the last 12 months</caption>
        <thead>
            <tr><th scope="col">Month</th><th scope="col">Assets</th><th scope="col">Liabilities</th><th scope="col">Net worth</th></tr>
        </thead>
        <tbody>
            <?php foreach ($netWorthTrend as $row): ?>
                <tr>
                    <th scope="row"><?= View::e($row['label']) ?></th>
                    <td><?= Money::format($row['assets']) ?></td>
                    <td><?= Money::format($row['liabilities']) ?></td>
                    <td><?= Money::format($row['netWorth']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
