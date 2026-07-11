<?php

/** @var list<array{label: string, income: string, expenses: string}> $incomeVsSpending */

use App\Support\Money;
use App\Support\View;

$hasActivity = array_sum(array_map(fn (array $r): float => (float) $r['income'] + (float) $r['expenses'], $incomeVsSpending)) > 0.0;

$chartPayload = [
    'labels' => array_column($incomeVsSpending, 'label'),
    'datasets' => [
        ['label' => 'Income', 'data' => array_map(fn (array $r): float => (float) $r['income'], $incomeVsSpending)],
        ['label' => 'Expenses', 'data' => array_map(fn (array $r): float => (float) $r['expenses'], $incomeVsSpending)],
    ],
];

?>
<div class="tile-header">
    <span class="label-text mb-0">Income vs. Spending</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if (!$hasActivity): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No transactions yet.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <div style="height: 220px;">
        <canvas id="chart-income-vs-spending" data-chart="bar" aria-label="Income versus spending over the last 6 months" role="img"></canvas>
    </div>
    <script type="application/json" id="chart-income-vs-spending-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <table class="sr-only">
        <caption>Income and spending by month for the last 6 months</caption>
        <thead>
            <tr><th scope="col">Month</th><th scope="col">Income</th><th scope="col">Expenses</th></tr>
        </thead>
        <tbody>
            <?php foreach ($incomeVsSpending as $row): ?>
                <tr>
                    <th scope="row"><?= View::e($row['label']) ?></th>
                    <td><?= Money::format($row['income']) ?></td>
                    <td><?= Money::format($row['expenses']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
