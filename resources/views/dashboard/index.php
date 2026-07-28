<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array|null $netWorth */
/** @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend */
/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var float|null $runwayMonths */
/** @var array{label: string, income: string, expenses: string, net: string, cumulative: string}|null $thisMonthCashFlow */
/** @var list<array{name: string, color: string|null, amount: string}> $incomeByCategory */
/** @var list<array{name: string, color: string|null, amount: string}> $topExpenseCategories */
/** @var list<string> $widgetOrder */
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'overview']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between flex-wrap gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white"><?= View::e($greeting) ?>, <?= View::e(explode(' ', $user['name'])[0]) ?></h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Your financial control center &middot; <?= View::e(gmdate('F Y')) ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/transactions/import" class="btn-secondary">Import Transactions</a>
                        <a href="/transactions/create" class="btn-primary">Add Transaction</a>
                        <button type="button" id="customize-open" class="btn-secondary">Customize</button>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-6 items-start">
                    <div class="flex-1 min-w-0 w-full">
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-3">Drag tiles to rearrange</p>
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
                                                'netWorthTrend' => $netWorthTrend,
                                                'allocationSummary' => $allocationSummary,
                                                'runwayMonths' => $runwayMonths,
                                                'thisMonthCashFlow' => $thisMonthCashFlow,
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
                    </div>

                    <aside class="dashboard-sidebar-sticky w-full lg:w-[360px] flex-shrink-0">
                        <div class="tile-card">
                            <?php View::partial('dashboard/widgets/lifecycle', ['allocationSummary' => $allocationSummary]); ?>
                        </div>
                    </aside>
                </div>

                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Business</h2>
                        <span class="badge-owner">New</span>
                    </div>

                    <div class="card text-center py-10" style="border-style: dashed;">
                        <div class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-stone-100 dark:bg-stone-800 text-stone-400 dark:text-stone-500 mb-3">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.4rem;height:1.4rem;" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                        </div>
                        <p class="text-stone-700 dark:text-stone-300 font-medium mb-1">No business accounts yet</p>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-4 max-w-md mx-auto">When your business gets going, add a business account and MyCFO+ will track its revenue, expenses, and profit separately from your household finances &mdash; still fully manual, still just yours.</p>
                        <a href="/business/overview" class="btn-secondary">Set up business tracking</a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                        <div class="card tile-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 15l4-5 4 3 5-7"/></svg>
                            <span class="text-sm font-medium">Business Profit</span>
                            <span class="text-xs mt-0.5">Coming soon</span>
                        </div>
                        <div class="card tile-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                            <span class="text-sm font-medium">Revenue by Stream</span>
                            <span class="text-xs mt-0.5">Coming soon</span>
                        </div>
                        <div class="card tile-placeholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z"/></svg>
                            <span class="text-sm font-medium">Business Insights</span>
                            <span class="text-xs mt-0.5">Coming soon</span>
                        </div>
                    </div>
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
</body>
</html>
