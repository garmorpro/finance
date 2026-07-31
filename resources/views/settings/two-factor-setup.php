<?php

/** @var string $secretFormatted */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Set up two-factor authentication · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/settings/security" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Security</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Set up two-factor authentication</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <div class="card space-y-4">
                    <div>
                        <h2 class="font-medium text-stone-900 dark:text-white mb-1">1. Add this account to your authenticator app</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-3">Open Google Authenticator, Authy, 1Password, or similar, and add a new account using a setup key (manual entry) rather than a QR code.</p>
                        <div class="rounded-lg bg-stone-50 dark:bg-stone-800 px-4 py-3 font-mono text-lg tracking-wider text-center text-stone-900 dark:text-white select-all">
                            <?= View::e($secretFormatted) ?>
                        </div>
                    </div>

                    <form method="POST" action="/settings/security/2fa/enable" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div>
                            <label for="code" class="field-label">2. Enter the 6-digit code it shows</label>
                            <input type="text" id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" class="field-input" placeholder="123456">
                            <p class="field-help">Confirms your app is generating matching codes before we turn this on.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="btn-primary">Enable two-factor authentication</button>
                            <a href="/settings/security" class="btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
