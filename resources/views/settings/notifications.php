<?php

/** @var array<string, string> $types */
/** @var array<string, bool> $preferences */
/** @var string $csrfToken */
/** @var string|null $notice */
/** @var string|null $error */

use App\Support\SettingsIcons;
use App\Support\View;

$descriptions = [
    'budget_over' => 'When a category has spent more than its planned budget this month.',
    'bills_due' => 'When a recurring bill or subscription is overdue.',
    'goal_milestones' => 'When a savings goal reaches 90% or more of its target.',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'notifications']); ?>

                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('notifications') ?></span>
                                <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Notifications</h2>
                            </div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Choose which conditions show up as an in-app alert (bell icon, top left). MyCFO+ doesn't send push notifications — everything here is in-app only. The one exception is the household-wide budget planning reminder email, configured under <a href="/settings/household" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Household</a>.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="/settings/notifications" class="card space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                            <?php foreach ($types as $type => $label): ?>
                                <label class="flex items-start gap-3 py-2 border-b border-stone-100 dark:border-stone-800 last:border-b-0">
                                    <input type="checkbox" name="enabled_types[]" value="<?= View::e($type) ?>" <?= $preferences[$type] ? 'checked' : '' ?> class="mt-1 rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                                    <span>
                                        <span class="block text-sm font-medium text-stone-900 dark:text-white"><?= View::e($label) ?></span>
                                        <span class="block text-sm text-stone-500 dark:text-stone-400"><?= View::e($descriptions[$type] ?? '') ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>

                            <button type="submit" class="btn-primary">Save preferences</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
