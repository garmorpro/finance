<?php

/** @var array{totalBalance: string, totalMinimumPayment: string, weightedAverageRate: float|null, count: int} $debtSummary */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Debt', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<?php if ($debtSummary['count'] === 0): ?>
    <div class="tile-placeholder">
        <p class="text-sm">No debt accounts.</p>
    </div>
<?php else: ?>
    <div class="text-2xl font-semibold text-red-600 dark:text-red-400"><?= Money::format($debtSummary['totalBalance']) ?></div>
    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
        <?= Money::format($debtSummary['totalMinimumPayment']) ?> min. payments / month
        <?php if ($debtSummary['weightedAverageRate'] !== null): ?>
            &middot; <?= $debtSummary['weightedAverageRate'] ?>% avg. rate
        <?php endif; ?>
    </p>
    <a href="/debt" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View debt &rarr;</a>
<?php endif; ?>
