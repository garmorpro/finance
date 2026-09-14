<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var array<int, array<string, mixed>> $quickAddAccounts */
/** @var string|null $newQuickAddKey */
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <?php View::partial('partials/_pwa_head'); ?>
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

                        <div class="card max-w-lg">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-4">Quick Add</h3>
                            <form method="POST" action="/settings/profile/quick-add" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div>
                                    <label for="quick_add_default_account_id" class="field-label">Default account</label>
                                    <select id="quick_add_default_account_id" name="quick_add_default_account_id" class="field-input">
                                        <option value="">No default &mdash; ask every time</option>
                                        <?php foreach ($quickAddAccounts as $account): ?>
                                            <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === (int) ($user['quick_add_default_account_id'] ?? 0) ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-help">Preselected on the Quick Add page (your home-screen icon's launch target) &mdash; you can still change it per transaction.</p>
                                </div>
                                <button type="submit" class="btn-primary">Save</button>
                            </form>
                        </div>

                        <div class="card max-w-lg">
                            <div class="flex items-start justify-between gap-4 mb-1">
                                <h2 class="font-medium text-stone-900 dark:text-white">Quick Add key</h2>
                                <span class="<?= $user['quick_add_key_hash'] !== null ? 'badge-owner' : 'badge' ?>"><?= $user['quick_add_key_hash'] !== null ? 'Active' : 'Not set up' ?></span>
                            </div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">Lets a device you add to your home screen skip signing in every time &mdash; enter this key once there and Quick Add stays ready from then on. It can only ever create a simple expense transaction; it can't sign in to the rest of the app, view balances, or change anything else.</p>

                            <?php if ($newQuickAddKey !== null): ?>
                                <div class="alert-success mb-4">
                                    <p class="font-semibold mb-1">Save this now &mdash; you won't see it again:</p>
                                    <p class="font-mono text-sm break-all select-all"><?= View::e($newQuickAddKey) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($user['quick_add_key_hash'] !== null): ?>
                                <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">
                                    Created <?= View::e((string) $user['quick_add_key_created_at']) ?><?= $user['quick_add_key_last_used_at'] !== null ? ' &middot; last used ' . View::e((string) $user['quick_add_key_last_used_at']) : ' &middot; never used yet' ?>
                                </p>

                                <details>
                                    <summary class="cursor-pointer text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Regenerate key</summary>
                                    <form method="POST" action="/settings/profile/quick-add-key" class="mt-3 space-y-3" onsubmit="return confirm('Replace your Quick Add key? Every device using the old one will need the new key entered again.');">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <div>
                                            <label for="quick_add_key_password" class="field-label">Confirm your password</label>
                                            <input type="password" id="quick_add_key_password" name="current_password" required autocomplete="current-password" class="field-input">
                                        </div>
                                        <button type="submit" class="btn-secondary">Regenerate</button>
                                    </form>
                                </details>

                                <form method="POST" action="/settings/profile/quick-add-key/revoke" class="mt-3" onsubmit="return confirm('Revoke your Quick Add key? Every device using it will need to sign in normally again.');">
                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:underline">Revoke key</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="/settings/profile/quick-add-key" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <div>
                                        <label for="quick_add_key_password" class="field-label">Confirm your password</label>
                                        <input type="password" id="quick_add_key_password" name="current_password" required autocomplete="current-password" class="field-input">
                                    </div>
                                    <button type="submit" class="btn-primary">Generate key</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
