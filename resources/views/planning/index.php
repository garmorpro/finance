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
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Planning</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Recurring bills, subscriptions, and savings goals, together.</p>
                </div>

                <div class="stat-hero <?= $netPositive ? 'stat-hero-positive' : 'stat-hero-negative' ?> flex items-center justify-between flex-wrap gap-6">
                    <span class="stat-hero-icon <?= $netPositive ? 'text-emerald-600' : 'text-red-600' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div style="position: relative;">
                        <div class="text-xs font-bold uppercase tracking-wide <?= $netPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?> mb-2">Net Monthly Recurring</div>
                        <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($netMonthlyRecurring) ?></div>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Recurring income minus recurring bills and subscriptions, per month.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(226, 105, 75, .14); color: #c94f32;"><?= $arrowDownIcon ?></span>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Bills &amp; Subscriptions</div>
                                <div class="text-xs <?= $recurringSummary['overdue'] > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-stone-500 dark:text-stone-400' ?>">
                                    <?= $recurringSummary['overdue'] > 0 ? $recurringSummary['overdue'] . ' overdue' : 'None overdue' ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($recurringSummary['monthlyExpense']) ?> <span class="text-sm font-normal text-stone-500 dark:text-stone-400">/ month</span></div>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $targetIcon ?></span>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Goals</div>
                                <div class="text-xs text-stone-500 dark:text-stone-400">Actively saving toward</div>
                            </div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $activeGoalCount ?> active</div>
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
