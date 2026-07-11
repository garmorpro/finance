<?php

/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <h1 class="text-xl font-semibold text-stone-900 dark:text-white mb-1">Forgot password</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400 mb-6">Enter your account email and we'll generate a reset link.</p>

        <?php if (!empty($notice)): ?>
            <div class="alert-success mb-4"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <form method="POST" action="/forgot-password" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" class="field-input">
            </div>
            <button type="submit" class="btn-primary btn-block">Send reset link</button>
        </form>
        <p class="mt-4 text-sm text-center">
            <a href="/login" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Back to login</a>
        </p>
    </div>
</body>
</html>
