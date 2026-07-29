<?php

/** @var list<array{label: string, accounts: list<array<string, mixed>>, total: string}> $sections */
/** @var array{assets: string, liabilities: string, net: string, count: int} $netWorth */
/** @var bool $hasAccounts */
/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\AccountTypeIcons;
use App\Support\AccountTypeLabels;
use App\Support\Money;
use App\Support\View;

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
            <div class="card">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <div class="label-text">Total Assets</div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($netWorth['assets']) ?></div>
                    </div>
                    <div>
                        <div class="label-text">Total Debt</div>
                        <div class="text-xl font-semibold text-red-600 dark:text-red-400"><?= Money::format($netWorth['liabilities']) ?></div>
                    </div>
                    <div>
                        <div class="label-text">Net Worth</div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($netWorth['net']) ?></div>
                    </div>
                </div>
            </div>

            <?php foreach ($sections as $section): ?>
                <div class="card">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white"><?= View::e($section['label']) ?></h2>
                        <span class="text-sm font-semibold text-stone-900 dark:text-white"><?= Money::format($section['total']) ?></span>
                    </div>
                    <div class="divide-y divide-stone-100 dark:divide-stone-800">
                        <?php foreach ($section['accounts'] as $account): ?>
                            <?php
                            $subtitle = AccountTypeLabels::label($account['account_type']);
                            if (!empty($account['institution_name'])) {
                                $subtitle .= ' · ' . $account['institution_name'];
                            }
                            $accountColor = $account['color'] ?: '#a8a29e';
                            ?>
                            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="flex items-center justify-between gap-4 py-3 -mx-2 px-2 rounded-lg first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg flex-shrink-0" style="background-color: <?= View::e($accountColor) ?>1a; color: <?= View::e($accountColor) ?>;">
                                        <?= AccountTypeIcons::svg($account['account_type']) ?>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($account['name']) ?></div>
                                        <div class="text-xs text-stone-500 dark:text-stone-400 truncate"><?= View::e($subtitle) ?></div>
                                    </div>
                                </div>
                                <span class="font-medium text-stone-900 dark:text-white flex-shrink-0"><?= Money::format($account['current_balance']) ?></span>
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
