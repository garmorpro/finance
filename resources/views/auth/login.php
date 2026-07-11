<?php

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
    <title>Log in · Finance</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white mb-6">Log in</h1>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success mb-4"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" class="field-input">
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="field-input">
            </div>
            <button type="submit" class="btn-primary btn-block">Log in</button>
        </form>
        <p class="mt-4 text-sm text-center">
            <a href="/forgot-password" class="text-blue-600 dark:text-blue-400 hover:underline">Forgot password?</a>
        </p>
    </div>
</body>
</html>
