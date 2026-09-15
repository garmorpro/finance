<?php use App\Support\View; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <?php View::partial('partials/_pwa_head'); ?>
    <title>Registration closed · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card text-center">
        <h1 class="text-lg font-semibold text-stone-900 dark:text-white mb-2">Registration is closed right now</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400">
            This household finance tracker isn't open to new signups at the moment.
            If you already have an account, <a href="/login" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">log in</a> instead.
        </p>
    </div>
</body>
</html>
