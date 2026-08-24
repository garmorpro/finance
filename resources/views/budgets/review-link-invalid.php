<?php

/** @var string|null $message */
/** @var bool|null $showLogout */

use App\Support\View;

$message = $message ?? "This link is invalid, expired, or has already been used.";
$showLogout = $showLogout ?? false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Budget review link · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card text-center">
        <h1 class="text-lg font-semibold text-stone-900 dark:text-white mb-2">This link won't work</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400"><?= View::e($message) ?></p>
        <p class="text-sm text-stone-500 dark:text-stone-400 mt-3">
            <?php if ($showLogout): ?>
                <form method="POST" action="/logout" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= View::e(\App\Support\Csrf::token()) ?>">
                    <button type="submit" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Log out</button>
                </form>
                and open the link again, or go to
                <a href="/budgets" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Budgets</a>.
            <?php else: ?>
                Go to <a href="/budgets" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Budgets</a>
                if you're already signed in, or
                <a href="/login" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">log in</a>.
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
