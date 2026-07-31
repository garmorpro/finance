<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\SettingsIcons;
use App\Support\View;

$nameParts = preg_split('/\s+/', trim($user['name']), -1, PREG_SPLIT_NO_EMPTY);
$initials = $nameParts !== false && $nameParts !== []
    ? strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''))
    : '?';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'profile']); ?>

                    <div class="space-y-6">
                        <div class="flex items-center gap-2.5">
                            <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('profile') ?></span>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Profile</h2>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <div class="card flex items-center gap-4">
                            <div class="flex items-center justify-center w-14 h-14 rounded-full bg-terracotta-600 text-white text-lg font-semibold flex-shrink-0"><?= View::e($initials) ?></div>
                            <div class="min-w-0">
                                <div class="text-lg font-semibold text-stone-900 dark:text-white truncate"><?= View::e($user['name']) ?></div>
                                <div class="text-sm text-stone-500 dark:text-stone-400 truncate"><?= View::e($user['email']) ?></div>
                                <?php if ($household !== null): ?>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-xs text-stone-500 dark:text-stone-400 truncate"><?= View::e($household['name']) ?></span>
                                        <span class="<?= $role === 'owner' ? 'badge-owner' : 'badge' ?> flex-shrink-0"><?= View::e(ucfirst((string) $role)) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card max-w-lg">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-4">Name &amp; email</h3>
                            <form method="POST" action="/settings/profile" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div>
                                    <label for="name" class="field-label">Name</label>
                                    <input type="text" id="name" name="name" value="<?= View::e($user['name']) ?>" required class="field-input">
                                </div>
                                <div>
                                    <label for="email" class="field-label">Email</label>
                                    <input type="email" id="email" name="email" value="<?= View::e($user['email']) ?>" required class="field-input">
                                </div>
                                <button type="submit" class="btn-primary">Save changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
