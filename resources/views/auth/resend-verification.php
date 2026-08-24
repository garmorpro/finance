<?php

/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Resend verification email · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <div class="flex flex-col items-center text-center mb-6">
            <h1 class="text-lg font-semibold text-stone-900 dark:text-white">Resend verification email</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Enter the email you signed up with.</p>
        </div>

        <?php if (!empty($notice)): ?>
            <div class="alert-success mb-4"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <form method="POST" action="/verify-email/resend" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" class="field-input" placeholder="you@example.com">
            </div>
            <button type="submit" class="btn-primary btn-block">Resend link</button>
        </form>

        <p class="mt-5 text-sm text-center">
            <a href="/login" class="text-terracotta-600 dark:text-terracotta-400 hover:underline font-medium">Back to log in</a>
        </p>
    </div>
</body>
</html>
