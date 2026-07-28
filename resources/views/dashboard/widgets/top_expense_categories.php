<?php

/** @var list<array{name: string, color: string|null, amount: string}> $topExpenseCategories */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Top Expense Categories', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<p class="text-xs text-stone-500 dark:text-stone-400 mb-4">What cost the most to run your household this month.</p>
<?php if ($topExpenseCategories === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No spending recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add a transaction &rarr;</a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($topExpenseCategories as $category): ?>
            <div class="flex items-center justify-between text-sm">
                <span class="text-stone-700 dark:text-stone-300"><?= View::e($category['name']) ?></span>
                <span class="font-medium text-stone-900 dark:text-white"><?= Money::format($category['amount']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
