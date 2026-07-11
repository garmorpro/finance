<?php

/** @var array $upcomingRecurring */
/** @var array{overdue: int, monthlyExpense: string, monthlyIncome: string} $recurringSummary */

use App\Support\Money;
use App\Support\View;

$today = gmdate('Y-m-d');

?>
<div class="tile-header">
    <span class="label-text mb-0">Recurring Bills</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($upcomingRecurring === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No recurring items yet.</p>
        <a href="/recurring/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <?php if ($recurringSummary['overdue'] > 0): ?>
        <p class="text-sm text-red-600 dark:text-red-400 font-medium mb-2">
            <?= $recurringSummary['overdue'] ?> overdue
        </p>
    <?php endif; ?>
    <ul class="space-y-2">
        <?php foreach ($upcomingRecurring as $item): ?>
            <li class="flex items-center justify-between text-sm">
                <div class="truncate">
                    <div class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($item['name']) ?></div>
                    <div class="text-xs <?= $item['next_due_date'] < $today ? 'text-red-600 dark:text-red-400' : 'text-stone-400 dark:text-stone-600' ?>"><?= View::e($item['next_due_date']) ?></div>
                </div>
                <span class="font-medium <?= $item['recurring_type'] === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                    <?= Money::format($item['expected_amount']) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="/recurring" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">
        View all &rarr;
    </a>
<?php endif; ?>
