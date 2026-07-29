<?php

/** @var list<array{label: string, accounts: list<array<string, mixed>>, total: string}> $sections */
/** @var array{assets: string, liabilities: string, net: string, count: int, assetCount: int, liabilityCount: int} $netWorth */
/** @var bool $hasAccounts */
/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\AccountTypeIcons;
use App\Support\AccountTypeLabels;
use App\Support\Money;
use App\Support\View;

$netPositive = bccomp($netWorth['net'], '0.00', 2) >= 0;
$arrowUpIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

        <div class="app-content">
        <main class="page-main-wide">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Accounts</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Manually tracked balances for your household.</p>
            </div>
            <a href="/accounts/create" class="btn-primary">Add account</a>
        </div>

        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <?php if (!$hasAccounts): ?>
            <div class="card text-center py-12">
                <p class="text-stone-500 dark:text-stone-400">No accounts yet.</p>
                <a href="/accounts/create" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add your first account &rarr;</a>
            </div>
        <?php else: ?>
            <div class="stat-hero <?= $netPositive ? 'stat-hero-positive' : 'stat-hero-negative' ?> flex items-center justify-between flex-wrap gap-6">
                <span class="stat-hero-icon <?= $netPositive ? 'text-emerald-600' : 'text-red-600' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
                <div style="position: relative;">
                    <div class="text-xs font-bold uppercase tracking-wide <?= $netPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?> mb-2">Net Worth</div>
                    <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($netWorth['net']) ?></div>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Total assets minus total debt, across every account included in net worth.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="card">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="metric-icon" style="background: rgba(52, 211, 153, .14); color: #059669;"><?= $arrowUpIcon ?></span>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Total Assets</div>
                            <div class="text-xs text-stone-500 dark:text-stone-400"><?= $netWorth['assetCount'] ?> account<?= $netWorth['assetCount'] === 1 ? '' : 's' ?></div>
                        </div>
                    </div>
                    <div class="text-xl font-semibold text-emerald-700 dark:text-emerald-400"><?= Money::format($netWorth['assets']) ?></div>
                </div>
                <div class="card">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="metric-icon" style="background: rgba(220, 38, 38, .14); color: #dc2626;"><?= $arrowDownIcon ?></span>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Total Debt</div>
                            <div class="text-xs text-stone-500 dark:text-stone-400"><?= $netWorth['liabilityCount'] ?> account<?= $netWorth['liabilityCount'] === 1 ? '' : 's' ?></div>
                        </div>
                    </div>
                    <div class="text-xl font-semibold text-red-600 dark:text-red-400"><?= Money::format($netWorth['liabilities']) ?></div>
                </div>
            </div>

            <?php foreach ($sections as $section): ?>
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white"><?= View::e($section['label']) ?></h2>
                        <span class="text-sm font-semibold text-stone-900 dark:text-white"><?= Money::format($section['total']) ?></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($section['accounts'] as $account): ?>
                            <?php $accountColor = $account['color'] ?: '#a8a29e'; ?>
                            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="account-card" style="background-color: <?= View::e($accountColor) ?>;">
                                <div class="flex items-start justify-between">
                                    <span class="account-card-icon"><?= AccountTypeIcons::svg($account['account_type']) ?></span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-white/70"><?= View::e(AccountTypeLabels::label($account['account_type'])) ?></span>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold truncate"><?= View::e($account['name']) ?></div>
                                    <?php if (!empty($account['institution_name'])): ?>
                                        <div class="text-xs text-white/70 truncate"><?= View::e($account['institution_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-end justify-between mt-2">
                                    <span class="text-sm tracking-widest text-white/60" aria-hidden="true">&bull;&bull;&bull;&bull;</span>
                                    <span class="text-lg font-semibold flex-shrink-0"><?= Money::format($account['current_balance']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </main>
        </div>
    </div>
</body>
</html>
