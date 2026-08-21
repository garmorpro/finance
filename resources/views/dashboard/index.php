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
/** @var list<array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}> $insights */
/** @var list<array<string, mixed>> $recentTransactions */
/** @var string $csrfToken */

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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                    <div class="card">
                        <?php View::partial('dashboard/widgets/insights', ['insights' => $insights]); ?>
                    </div>
                    <div class="card">
                        <?php View::partial('dashboard/widgets/recent_transactions', ['recentTransactions' => $recentTransactions]); ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
                    <div class="card">
                        <?php View::partial('dashboard/widgets/income_by_source', ['incomeByCategory' => $incomeByCategory]); ?>
                    </div>
                    <div class="card">
                        <?php View::partial('dashboard/widgets/top_expense_categories', ['topExpenseCategories' => $topExpenseCategories]); ?>
                    </div>
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
</body>
</html>
