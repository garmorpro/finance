<?php

/** @var bool $twoFactorEnabled */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security · Settings · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'security']); ?>

                    <div class="space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Security</h2>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Change your password.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <div class="card max-w-lg">
                            <form method="POST" action="/settings/security" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div>
                                    <label for="current_password" class="field-label">Current password</label>
                                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password" class="field-input">
                                </div>
                                <div>
                                    <label for="new_password" class="field-label">New password</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password" class="field-input">
                                    <p class="field-help">At least 12 characters.</p>
                                </div>
                                <div>
                                    <label for="new_password_confirmation" class="field-label">Confirm new password</label>
                                    <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="12" autocomplete="new-password" class="field-input">
                                </div>
                                <button type="submit" class="btn-primary">Update password</button>
                            </form>
                        </div>

                        <div class="card max-w-lg">
                            <div class="flex items-start justify-between gap-4 mb-1">
                                <h2 class="font-medium text-stone-900 dark:text-white">Two-factor authentication</h2>
                                <span class="<?= $twoFactorEnabled ? 'badge-owner' : 'badge' ?>"><?= $twoFactorEnabled ? 'On' : 'Off' ?></span>
                            </div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">Require a code from an authenticator app in addition to your password when signing in.</p>

                            <?php if ($twoFactorEnabled): ?>
                                <details>
                                    <summary class="cursor-pointer text-sm font-medium text-red-600 dark:text-red-400 hover:underline">Turn off two-factor authentication</summary>
                                    <form method="POST" action="/settings/security/2fa/disable" class="mt-3 space-y-3" onsubmit="return confirm('Turn off two-factor authentication? Your account will only need a password to sign in.');">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <div>
                                            <label for="disable_2fa_password" class="field-label">Confirm your password</label>
                                            <input type="password" id="disable_2fa_password" name="current_password" required autocomplete="current-password" class="field-input">
                                        </div>
                                        <button type="submit" class="btn-secondary">Turn off</button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <a href="/settings/security/2fa/setup" class="btn-primary inline-block">Set up two-factor authentication</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
