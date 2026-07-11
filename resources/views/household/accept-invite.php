<?php

/** @var string $token */
/** @var string $email */
/** @var string $role */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept invitation · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <h1 class="text-xl font-semibold text-stone-900 dark:text-white mb-1">Join as <?= View::e(ucfirst($role)) ?></h1>
        <p class="text-sm text-stone-500 dark:text-stone-400 mb-6"><?= View::e($email) ?></p>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/accept-invite" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= View::e($token) ?>">
            <div>
                <label for="name" class="field-label">Your name</label>
                <input type="text" id="name" name="name" required class="field-input">
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password" class="field-input">
                <p class="field-help">At least 12 characters.</p>
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="12" autocomplete="new-password" class="field-input">
            </div>
            <button type="submit" class="btn-primary btn-block">Create account</button>
        </form>
    </div>
</body>
</html>
