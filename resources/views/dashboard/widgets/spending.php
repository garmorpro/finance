<?php

/** @var list<array{name: string, color: string|null, amount: string}> $spendingByCategory */

use App\Support\Money;
use App\Support\View;

$palette = ['#c94f32', '#059669', '#a8a29e', '#e88562', '#78716c', '#f0a488', '#57534e', '#fbe4dc'];

$chartPayload = [
    'labels' => array_column($spendingByCategory, 'name'),
    'datasets' => [[
        'data' => array_map(fn (array $r): float => (float) $r['amount'], $spendingByCategory),
        'backgroundColor' => array_map(
            fn (array $r, int $i): string => $r['color'] ?? $palette[$i % count($palette)],
            $spendingByCategory,
            array_keys($spendingByCategory)
        ),
    ]],
];

?>
<div class="tile-header">
    <span class="label-text mb-0">Spending by Category</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($spendingByCategory === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No spending recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add a transaction &rarr;</a>
    </div>
<?php else: ?>
    <div style="height: 220px;">
        <canvas id="chart-spending-by-category" data-chart="doughnut" aria-label="Spending by category this month" role="img"></canvas>
    </div>
    <script type="application/json" id="chart-spending-by-category-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <table class="sr-only">
        <caption>Spending by category this month</caption>
        <thead>
            <tr><th scope="col">Category</th><th scope="col">Amount</th></tr>
        </thead>
        <tbody>
            <?php foreach ($spendingByCategory as $row): ?>
                <tr>
                    <th scope="row"><?= View::e($row['name']) ?></th>
                    <td><?= Money::format($row['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
