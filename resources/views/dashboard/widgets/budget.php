<?php

/** @var array{planned: string, spent: string, remaining: string, has_items: bool} $budgetSummary */

use App\Support\Money;
use App\Support\View;

?>
<div class="tile-header">
    <span class="label-text mb-0">Budget</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if (!$budgetSummary['has_items']): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No budget set for this month yet.</p>
        <a href="/budgets" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Set one up &rarr;</a>
    </div>
<?php else: ?>
    <div class="flex items-baseline justify-between mb-2">
        <span class="text-2xl font-semibold <?= bccomp($budgetSummary['remaining'], '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>">
            <?= Money::format($budgetSummary['remaining']) ?>
        </span>
        <span class="text-sm text-stone-500 dark:text-stone-400">
            <?= bccomp($budgetSummary['remaining'], '0.00', 2) < 0 ? 'over budget' : 'remaining' ?>
        </span>
    </div>
    <p class="text-sm text-stone-500 dark:text-stone-400"><?= Money::format($budgetSummary['spent']) ?> spent of <?= Money::format($budgetSummary['planned']) ?> planned</p>
    <a href="/budgets" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View budget &rarr;</a>
<?php endif; ?>
