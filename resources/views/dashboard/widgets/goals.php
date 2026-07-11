<?php

/** @var array $topGoals */
/** @var int $activeGoalCount */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Goals', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<?php if ($topGoals === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No goals yet.</p>
        <a href="/goals/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($topGoals as $goal): ?>
            <li>
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($goal['name']) ?></span>
                    <span class="text-stone-500 dark:text-stone-400"><?= $goal['progressPercent'] ?>%</span>
                </div>
                <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full bg-terracotta-600" style="width: <?= max(0, min(100, $goal['progressPercent'])) ?>%"></div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="/goals" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">
        View all <?= $activeGoalCount ?> &rarr;
    </a>
<?php endif; ?>
