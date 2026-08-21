<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array|null $netWorth */
/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */
/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var float|null $runwayMonths */
/** @var array{label: string, income: string, expenses: string, net: string, cumulative: string}|null $thisMonthCashFlow */
/** @var list<array{id: int, name: string, color: string|null, amount: string}> $incomeByCategory */
/** @var list<array{name: string, color: string|null, amount: string}> $topExpenseCategories */
/** @var list<string> $mainOrder */
/** @var list<string> $hiddenWidgets */
/** @var list<string> $wideWidgets */
/** @var string $csrfToken */

use App\Support\DashboardWidgets;
use App\Support\View;

$hour = (int) gmdate('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Overview · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'overview']); ?>

        <div class="app-content">
            <main class="page-main-wide">

                <div class="dash-hero">
                    <div class="dash-hero-top">
                        <div>
                            <h1><?= View::e($greeting) ?>, <?= View::e(explode(' ', $user['name'])[0]) ?></h1>
                            <p class="sub">Your financial control center &middot; <?= View::e(gmdate('F Y')) ?></p>
                        </div>
                        <div class="dash-hero-actions">
                            <a href="/transactions/import" class="btn-ghost-dark">Import Transactions</a>
                            <a href="/transactions/create" class="btn-primary">Add Transaction</a>
                            <button type="button" id="customize-open" class="btn-ghost-dark">Customize</button>
                        </div>
                    </div>

                    <div class="dash-hero-body">
                        <?php View::partial('dashboard/widgets/hero_stats', [
                            'netWorth' => $netWorth,
                            'netWorthTrend' => $netWorthTrend,
                            'runwayMonths' => $runwayMonths,
                            'allocationSummary' => $allocationSummary,
                            'thisMonthCashFlow' => $thisMonthCashFlow,
                        ]); ?>
                    </div>
                </div>

                <div class="card">
                    <?php View::partial('dashboard/widgets/waterfall', ['allocationSummary' => $allocationSummary]); ?>
                </div>

                <div id="dashboard-grid" class="dashboard-grid" data-csrf-token="<?= View::e($csrfToken) ?>">
                    <?php foreach ($mainOrder as $widgetKey): ?>
                        <?php $isWide = in_array($widgetKey, $wideWidgets, true); ?>
                        <div class="tile <?= $isWide ? 'tile-wide' : '' ?> <?= in_array($widgetKey, $hiddenWidgets, true) ? 'hidden' : '' ?>" draggable="true" data-widget="<?= View::e($widgetKey) ?>">
                            <div class="tile-card">
                                <?php if (DashboardWidgets::isAvailable($widgetKey)): ?>
                                    <?php View::partial('dashboard/widgets/' . $widgetKey, [
                                        'widgetKey' => $widgetKey,
                                        'isWide' => $isWide,
                                        'incomeByCategory' => $incomeByCategory,
                                        'topExpenseCategories' => $topExpenseCategories,
                                    ]); ?>
                                <?php else: ?>
                                    <?php View::partial('dashboard/widgets/coming-soon', ['title' => DashboardWidgets::title($widgetKey)]); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Business</h2>
                        <span class="badge-owner">New</span>
                    </div>

                    <div class="biz-banner">
                        <span class="biz-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.4rem;height:1.4rem;" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                        </span>
                        <div class="biz-copy">
                            <p class="title">No business accounts yet</p>
                            <p class="desc">Track revenue, expenses, and profit separately from your household finances &mdash; still fully manual, still just yours.</p>
                        </div>
                        <a href="/business/overview" class="btn-secondary" style="flex-shrink:0;">Set up business tracking</a>
                    </div>
                </div>

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
                <?php foreach ($mainOrder as $widgetKey): ?>
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
</body>
</html>
