<?php use App\Support\View; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Link expired · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card text-center">
        <h1 class="text-lg font-semibold text-stone-900 dark:text-white mb-2">This link is invalid or has expired</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400">
            <a href="/forgot-password" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Request a new reset link</a>
        </p>
    </div>
</body>
</html>
