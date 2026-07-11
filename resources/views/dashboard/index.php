<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array|null $netWorth */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview · Finance</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="page-shell">
    <?php View::partial('partials/nav', ['csrfToken' => $csrfToken, 'active' => 'overview']); ?>

    <main class="page-main">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Overview</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Welcome back, <?= View::e(explode(' ', $user['name'])[0]) ?>.</p>
        </div>

        <?php if ($netWorth !== null): ?>
        <div class="grid grid-cols-3 gap-4">
            <div class="card">
                <div class="label-text">Net worth</div>
                <div class="text-xl font-semibold text-slate-900 dark:text-white mt-1"><?= Money::format($netWorth['net']) ?></div>
            </div>
            <div class="card">
                <div class="label-text">Total assets</div>
                <div class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 mt-1"><?= Money::format($netWorth['assets']) ?></div>
            </div>
            <div class="card">
                <div class="label-text">Total debt</div>
                <div class="text-xl font-semibold text-red-600 dark:text-red-400 mt-1"><?= Money::format($netWorth['liabilities']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="flex items-center justify-between">
                <div class="label-text">Accounts</div>
                <a href="/accounts" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Manage &rarr;</a>
            </div>
            <div class="text-base text-slate-900 dark:text-white mt-1">
                <?= $netWorth !== null ? (int) $netWorth['count'] : 0 ?> account<?= ($netWorth['count'] ?? 0) === 1 ? '' : 's' ?>
            </div>
        </div>

        <div class="card">
            <div class="label-text">Logged in as</div>
            <div class="text-base text-slate-900 dark:text-white"><?= View::e($user['name']) ?></div>
            <div class="text-sm text-slate-500 dark:text-slate-400"><?= View::e($user['email']) ?></div>
        </div>

        <?php if ($household !== null): ?>
        <div class="card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="label-text">Household</div>
                    <div class="text-base text-slate-900 dark:text-white"><?= View::e($household['name']) ?></div>
                </div>
                <span class="<?= $role === 'owner' ? 'badge-owner' : 'badge' ?>"><?= View::e(ucfirst((string) $role)) ?></span>
            </div>
            <a href="/household" class="inline-block mt-4 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Manage household &rarr;</a>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
