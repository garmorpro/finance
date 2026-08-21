<?php

/** @var list<array{name: string, color: string|null, amount: string}> $topExpenseCategories */

use App\Support\Money;
use App\Support\View;

// Relative-spend bar per row, scaled against the largest amount in the
// list — spendingByCategory() already returns rows sorted highest to
// lowest, so the first row is always the 100% bar.
$maxAmount = $topExpenseCategories !== [] ? $topExpenseCategories[0]['amount'] : '0.00';

?>
<h3 class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white mb-3">Top Expense Categories</h3>
<p class="text-xs text-stone-500 dark:text-stone-400 mb-4">What cost the most to run your household this month.</p>
<?php if ($topExpenseCategories === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No spending recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add a transaction &rarr;</a>
    </div>
<?php else: ?>
    <div class="exp-list">
        <?php foreach ($topExpenseCategories as $category): ?>
            <?php $pct = bccomp($maxAmount, '0.00', 2) > 0 ? (float) bcdiv(bcmul($category['amount'], '100', 4), $maxAmount, 4) : 0.0; ?>
            <div class="exp-row">
                <div class="exp-top">
                    <span class="text-stone-700 dark:text-stone-300"><?= View::e($category['name']) ?></span>
                    <span class="exp-amt tabular-nums"><?= Money::format($category['amount']) ?></span>
                </div>
                <div class="exp-bar-track"><div class="exp-bar-fill" style="width:<?= max(4, $pct) ?>%;"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
