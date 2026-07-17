<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array|null $netWorth */
/** @var array $accounts */
/** @var array $recentTransactions */
/** @var array{planned: string, spent: string, remaining: string, has_items: bool} $budgetSummary */
/** @var array $upcomingRecurring */
/** @var array{overdue: int, monthlyExpense: string, monthlyIncome: string} $recurringSummary */
/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */
/** @var list<array{label: string, income: string, expenses: string}> $incomeVsSpending */
/** @var list<array{name: string, color: string|null, amount: string}> $spendingByCategory */
/** @var array $topGoals */
/** @var int $activeGoalCount */
/** @var array{totalBalance: string, totalMinimumPayment: string, weightedAverageRate: float|null, count: int} $debtSummary */
/** @var list<string> $widgetOrder */
/** @var list<string> $hiddenWidgets */
/** @var list<string> $wideWidgets */
/** @var string $csrfToken */

use App\Support\DashboardWidgets;
use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'overview']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Hello, <?= View::e(explode(' ', $user['name'])[0]) ?>!</h1>
                    <div class="flex items-center gap-4">
                        <p class="text-xs text-stone-500 dark:text-stone-400">Drag tiles to rearrange</p>
                        <button type="button" id="customize-open" class="btn-secondary">Customize</button>
                    </div>
                </div>

                <div id="dashboard-grid" class="dashboard-grid" data-csrf-token="<?= View::e($csrfToken) ?>">
                    <?php foreach ($widgetOrder as $widgetKey): ?>
                        <?php $isWide = in_array($widgetKey, $wideWidgets, true); ?>
                        <div class="tile <?= $isWide ? 'tile-wide' : '' ?> <?= in_array($widgetKey, $hiddenWidgets, true) ? 'hidden' : '' ?>" draggable="true" data-widget="<?= View::e($widgetKey) ?>">
                            <div class="tile-card">
                                <?php if (DashboardWidgets::isAvailable($widgetKey)): ?>
                                    <?php View::partial('dashboard/widgets/' . $widgetKey, [
                                        'widgetKey' => $widgetKey,
                                        'isWide' => $isWide,
                                        'netWorth' => $netWorth,
                                        'accounts' => $accounts,
                                        'recentTransactions' => $recentTransactions,
                                        'budgetSummary' => $budgetSummary,
                                        'upcomingRecurring' => $upcomingRecurring,
                                        'recurringSummary' => $recurringSummary,
                                        'netWorthTrend' => $netWorthTrend,
                                        'incomeVsSpending' => $incomeVsSpending,
                                        'spendingByCategory' => $spendingByCategory,
                                        'topGoals' => $topGoals,
                                        'activeGoalCount' => $activeGoalCount,
                                        'debtSummary' => $debtSummary,
                                    ]); ?>
                                <?php else: ?>
                                    <?php View::partial('dashboard/widgets/coming-soon', ['title' => DashboardWidgets::title($widgetKey)]); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($household !== null): ?>
                <div class="card max-w-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="label-text">Household</div>
                            <div class="text-base text-stone-900 dark:text-white"><?= View::e($household['name']) ?></div>
                        </div>
                        <span class="<?= $role === 'owner' ? 'badge-owner' : 'badge' ?>"><?= View::e(ucfirst((string) $role)) ?></span>
                    </div>
                    <a href="/settings/household" class="inline-block mt-4 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Manage household &rarr;</a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div id="customize-modal" class="hidden fixed inset-0 z-30 items-center justify-center bg-stone-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="customize-modal-title">
        <div class="card w-full max-w-md max-h-[80vh] overflow-y-auto">
            <div class="flex items-start justify-between mb-1">
                <h2 id="customize-modal-title" class="text-lg font-semibold text-stone-900 dark:text-white">Customize dashboard</h2>
                <button type="button" id="customize-close" class="text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200" aria-label="Close">&times;</button>
            </div>
            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">Choose which tiles appear on your overview.</p>

            <div id="customize-list" class="space-y-1" data-csrf-token="<?= View::e($csrfToken) ?>">
                <?php foreach ($widgetOrder as $widgetKey): ?>
                    <?php $isHidden = in_array($widgetKey, $hiddenWidgets, true); ?>
                    <label class="flex items-center justify-between py-2 px-2 rounded-lg hover:bg-stone-50 dark:hover:bg-stone-800 cursor-pointer">
                        <span class="text-sm font-medium text-stone-700 dark:text-stone-300"><?= View::e(DashboardWidgets::title($widgetKey)) ?></span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" data-widget-toggle="<?= View::e($widgetKey) ?>" class="sr-only peer" <?= $isHidden ? '' : 'checked' ?>>
                            <span class="w-11 h-6 bg-stone-300 dark:bg-stone-700 rounded-full peer-checked:bg-terracotta-600 transition-colors"></span>
                            <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="<?= View::asset('/assets/js/dashboard.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/vendor/chart.umd.min.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/charts.js') ?>" defer></script>
</body>
</html>
