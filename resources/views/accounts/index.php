<?php

/** @var list<array{label: string, accounts: list<array<string, mixed>>, total: string}> $sections */
/** @var array{assets: string, liabilities: string, net: string, count: int, assetCount: int, liabilityCount: int} $netWorth */
/** @var bool $hasAccounts */
/** @var string $csrfToken */
/** @var string|null $notice */

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Accounts · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

        <div class="app-content">
        <main class="page-main-wide">
        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <?php if (!$hasAccounts): ?>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Accounts</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Manually tracked balances for your household.</p>
                </div>
                <a href="/accounts/create" class="btn-primary">Add account</a>
            </div>
            <div class="card text-center py-12">
                <p class="text-stone-500 dark:text-stone-400">No accounts yet.</p>
                <a href="/accounts/create" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add your first account &rarr;</a>
            </div>
        <?php else: ?>
            <div class="dash-hero">
                <div class="dash-hero-top">
                    <div>
                        <h1>Accounts</h1>
                        <p class="sub">Manually tracked balances for your household.</p>
                    </div>
                    <div class="dash-hero-actions">
                        <a href="/accounts/create" class="btn-primary">Add account</a>
                    </div>
                </div>

                <div class="dash-hero-body">
                    <div class="dash-hero-nw">
                        <p class="dash-hero-eyebrow">Net Worth</p>
                        <div class="dash-hero-nw-value tabular-nums" style="<?= $netPositive ? '' : 'color:#fca5a5;' ?>"><?= Money::format($netWorth['net']) ?></div>
                        <p class="dash-hero-nw-caption" style="margin-top:0.85rem; max-width:42ch;">Total assets minus total debt, across every account included in net worth.</p>
                    </div>

                    <div class="dash-hero-stats">
                        <div class="dash-hero-stat">
                            <span class="dash-hero-stat-icon income"><?= $arrowUpIcon ?></span>
                            <span>
                                <span class="dash-hero-stat-label">Total Assets &middot; <?= $netWorth['assetCount'] ?> account<?= $netWorth['assetCount'] === 1 ? '' : 's' ?></span>
                                <span class="dash-hero-stat-value tabular-nums"><?= Money::format($netWorth['assets']) ?></span>
                            </span>
                        </div>
                        <div class="dash-hero-stat">
                            <span class="dash-hero-stat-icon expense"><?= $arrowDownIcon ?></span>
                            <span>
                                <span class="dash-hero-stat-label">Total Debt &middot; <?= $netWorth['liabilityCount'] ?> account<?= $netWorth['liabilityCount'] === 1 ? '' : 's' ?></span>
                                <span class="dash-hero-stat-value tabular-nums"><?= Money::format($netWorth['liabilities']) ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Type</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr class="table-group-row">
                                <td colspan="3">
                                    <?= View::e($section['label']) ?>
                                    <span class="table-group-row-total tabular-nums"><?= Money::format($section['total']) ?></span>
                                </td>
                            </tr>
                            <?php foreach ($section['accounts'] as $account): ?>
                                <?php $accountColor = $account['color'] ?: '#a8a29e'; ?>
                                <tr>
                                    <td>
                                        <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="inline-flex items-center gap-2 font-medium text-stone-900 dark:text-white hover:text-terracotta-600 dark:hover:text-terracotta-400">
                                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($accountColor) ?>;"></span>
                                            <?= View::e($account['name']) ?>
                                        </a>
                                        <?php if (!empty($account['institution_name'])): ?>
                                            <span class="text-stone-500 dark:text-stone-400">&middot; <?= View::e($account['institution_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-stone-500 dark:text-stone-400"><?= View::e(AccountTypeLabels::label($account['account_type'])) ?></td>
                                    <td class="text-right font-medium text-stone-900 dark:text-white tabular-nums"><?= Money::format($account['current_balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </main>
        </div>
    </div>
</body>
</html>
