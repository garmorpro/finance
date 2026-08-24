<?php

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Check your email · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card text-center">
        <div class="w-12 h-12 rounded-2xl bg-terracotta-600 flex items-center justify-center mb-4 shadow-sm mx-auto">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="M3 7l9 6 9-6" />
            </svg>
        </div>
        <h1 class="text-lg font-semibold text-stone-900 dark:text-white mb-2">Check your email</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400">
            Your household was created. We sent a confirmation link to the email you signed up with —
            click it to verify your address and sign in.
        </p>
        <p class="text-sm text-stone-500 dark:text-stone-400 mt-3">
            Didn't get it? <a href="/verify-email/resend" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Resend the link</a>.
        </p>
    </div>
</body>
</html>
