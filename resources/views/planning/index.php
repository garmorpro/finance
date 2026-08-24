<?php

/** @var array $upcomingRecurring */
/** @var array{overdue: int, monthlyExpense: string, monthlyIncome: string} $recurringSummary */
/** @var array $topGoals */
/** @var int $activeGoalCount */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$today = gmdate('Y-m-d');

$netMonthlyRecurring = bcsub($recurringSummary['monthlyIncome'], $recurringSummary['monthlyExpense'], 2);
$netPositive = bccomp($netMonthlyRecurring, '0.00', 2) >= 0;

$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
$targetIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Planning · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'planning']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="dash-hero">
                    <div class="dash-hero-top">
                        <div>
                            <h1>Planning</h1>
                            <p class="sub">Recurring bills, subscriptions, and savings goals, together.</p>
                        </div>
                        <div class="dash-hero-actions">
                            <a href="/recurring/create" class="btn-ghost-dark">Add recurring</a>
                            <a href="/goals/create" class="btn-primary">Add goal</a>
                        </div>
                    </div>

                    <div class="dash-hero-body">
                        <div class="dash-hero-nw">
                            <p class="dash-hero-eyebrow">Net Monthly Recurring</p>
                            <div class="dash-hero-nw-value tabular-nums" style="<?= $netPositive ? '' : 'color:#fca5a5;' ?>"><?= Money::format($netMonthlyRecurring) ?></div>
                            <p class="dash-hero-nw-caption" style="margin-top:0.85rem; max-width:42ch;">Recurring income minus recurring bills and subscriptions, per month.</p>
                        </div>

                        <div class="dash-hero-stats">
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon expense"><?= $arrowDownIcon ?></span>
                                <span>
                                    <span class="dash-hero-stat-label">Bills &amp; Subscriptions <?= $recurringSummary['overdue'] > 0 ? '&middot; ' . $recurringSummary['overdue'] . ' overdue' : '' ?></span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= Money::format($recurringSummary['monthlyExpense']) ?></span>
                                </span>
                            </div>
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon"><?= $targetIcon ?></span>
                                <span>
                                    <span class="dash-hero-stat-label">Goals</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= $activeGoalCount ?> active</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="card">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-stone-900 dark:text-white">Recurring Bills &amp; Subscriptions</h2>
                            <a href="/recurring" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all &rarr;</a>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">
                            <?= Money::format($recurringSummary['monthlyExpense']) ?> in bills, <?= Money::format($recurringSummary['monthlyIncome']) ?> in recurring income, per month.
                        </p>

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
                            <ul class="space-y-3">
                                <?php foreach ($upcomingRecurring as $item): ?>
                                    <li class="flex items-center justify-between text-sm">
                                        <div class="truncate">
                                            <div class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($item['name']) ?></div>
                                            <div class="text-xs <?= $item['next_due_date'] < $today ? 'text-red-600 dark:text-red-400' : 'text-stone-500 dark:text-stone-400' ?>"><?= View::e($item['next_due_date']) ?></div>
                                        </div>
                                        <span class="font-medium <?= $item['recurring_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                                            <?= Money::format($item['expected_amount']) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-stone-900 dark:text-white">Goals</h2>
                            <a href="/goals" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all &rarr;</a>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">
                            <?= $activeGoalCount ?> active goal<?= $activeGoalCount === 1 ? '' : 's' ?>.
                        </p>

                        <?php if ($topGoals === []): ?>
                            <div class="tile-placeholder">
                                <p class="text-sm mb-2">No goals yet.</p>
                                <a href="/goals/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
                            </div>
                        <?php else: ?>
                            <ul class="space-y-4">
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
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
