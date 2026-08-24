<?php

/** @var string $turnstileSiteKey */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var array<string, string> $old */

use App\Support\View;

$old = $old ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create your household · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
    <?php if ($turnstileSiteKey !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
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
            <h1 class="text-xl font-semibold text-stone-900 dark:text-white">Create your household</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">You'll be the Owner — invite the rest of your household afterward from Settings.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

            <div>
                <label for="name" class="field-label">Your name</label>
                <input type="text" id="name" name="name" required autocomplete="name" class="field-input" value="<?= View::e($old['name'] ?? '') ?>">
            </div>
            <div>
                <label for="household_name" class="field-label">Household name</label>
                <input type="text" id="household_name" name="household_name" required class="field-input" placeholder="e.g. Our Household" value="<?= View::e($old['household_name'] ?? '') ?>">
            </div>
            <div>
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" class="field-input" placeholder="you@example.com" value="<?= View::e($old['email'] ?? '') ?>">
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="12" class="field-input" placeholder="At least 12 characters">
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" minlength="12" class="field-input">
            </div>

            <?php if ($turnstileSiteKey !== ''): ?>
                <div class="cf-turnstile" data-sitekey="<?= View::e($turnstileSiteKey) ?>"></div>
            <?php endif; ?>

            <button type="submit" class="btn-primary btn-block">Create household</button>
        </form>

        <p class="mt-5 text-sm text-center">
            Already have an account?
            <a href="/login" class="text-terracotta-600 dark:text-terracotta-400 hover:underline font-medium">Log in</a>
        </p>
    </div>
</body>
</html>
