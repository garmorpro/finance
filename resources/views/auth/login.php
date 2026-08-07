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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Log in · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="absolute inset-0 overflow-hidden pointer-events-none -z-10" aria-hidden="true">
        <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[36rem] h-[36rem] rounded-full bg-terracotta-400/20 dark:bg-terracotta-600/10 blur-3xl"></div>
    </div>

    <div class="auth-card">
        <div class="flex flex-col items-center text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-terracotta-600 flex items-center justify-center mb-4 shadow-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6" aria-hidden="true">
                    <path d="M3 12l3-3 4 4 5-6 6 5" />
                    <path d="M14 6h6v6" />
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-stone-900 dark:text-white">Welcome back</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Log in to your household finance dashboard.</p>
        </div>

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
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400 dark:text-stone-500" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M3 7l9 6 9-6" />
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" required autocomplete="username" class="field-input pl-9" placeholder="you@example.com">
                </div>
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400 dark:text-stone-500" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                            <rect x="5" y="11" width="14" height="9" rx="2" />
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" required autocomplete="current-password" class="field-input pl-9 pr-10" placeholder="••••••••">
                    <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3 text-stone-400 dark:text-stone-500 hover:text-stone-600 dark:hover:text-stone-300 cursor-pointer" aria-label="Show password">
                        <svg id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg id="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 hidden" aria-hidden="true">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                            <line x1="1" y1="1" x2="23" y2="23" />
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-primary btn-block">Log in</button>
        </form>

        <div class="flex items-center gap-3 my-5" aria-hidden="true">
            <div class="flex-1 h-px bg-stone-200 dark:bg-stone-800"></div>
            <span class="text-xs font-medium text-stone-400 dark:text-stone-600">or</span>
            <div class="flex-1 h-px bg-stone-200 dark:bg-stone-800"></div>
        </div>

        <button type="button" id="webauthn-login" class="btn-secondary btn-block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="16" r="1.5"/><path d="M7 11V7a5 5 0 0 1 10 0"/></svg>
            Sign in with a passkey
        </button>
        <p id="webauthn-login-status" class="text-sm text-center text-stone-500 dark:text-stone-400 mt-2"></p>

        <p class="mt-5 text-sm text-center">
            <a href="/forgot-password" class="text-terracotta-600 dark:text-terracotta-400 hover:underline font-medium">Forgot password?</a>
        </p>
    </div>

    <script src="<?= View::asset('/assets/js/webauthn.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/auth.js') ?>" defer></script>
</body>
</html>
