<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array|null $netWorth */
/** @var array $accounts */
/** @var list<string> $widgetOrder */
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
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="page-shell">
    <?php View::partial('partials/nav', ['csrfToken' => $csrfToken, 'active' => 'overview']); ?>

    <main class="page-main-wide">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Overview</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Welcome back, <?= View::e(explode(' ', $user['name'])[0]) ?>.</p>
            </div>
            <p class="text-xs text-slate-400 dark:text-slate-600">Drag tiles to rearrange</p>
        </div>

        <div id="dashboard-grid" class="dashboard-grid" data-csrf-token="<?= View::e($csrfToken) ?>">
            <?php foreach ($widgetOrder as $widgetKey): ?>
                <div class="tile" draggable="true" data-widget="<?= View::e($widgetKey) ?>">
                    <?php if (DashboardWidgets::isAvailable($widgetKey)): ?>
                        <?php View::partial('dashboard/widgets/' . $widgetKey, [
                            'netWorth' => $netWorth,
                            'accounts' => $accounts,
                        ]); ?>
                    <?php else: ?>
                        <?php View::partial('dashboard/widgets/coming-soon', ['title' => DashboardWidgets::title($widgetKey)]); ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
        </div>
    </main>

    <script src="/assets/js/dashboard.js" defer></script>
</body>
</html>
