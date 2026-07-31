<?php

/** @var string $token */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reset password · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <h1 class="text-xl font-semibold text-stone-900 dark:text-white mb-6">Reset password</h1>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/reset-password" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= View::e($token) ?>">
            <div>
                <label for="password" class="field-label">New password</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password" class="field-input">
                <p class="field-help">At least 12 characters.</p>
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="12" autocomplete="new-password" class="field-input">
            </div>
            <button type="submit" class="btn-primary btn-block">Reset password</button>
        </form>
    </div>
</body>
</html>
