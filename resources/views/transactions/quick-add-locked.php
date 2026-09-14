<?php

/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <?php View::partial('partials/_pwa_head'); ?>
    <title>Quick add · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="quickadd-page">
    <div class="quickadd-modal">
        <div class="flex items-center gap-3 mb-1">
            <span class="quickadd-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="width:1.2rem;height:1.2rem;" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            </span>
            <div>
                <h1 class="text-[1.0625rem] font-bold tracking-tight text-stone-900 dark:text-white">Quick add</h1>
                <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">Enter your Quick Add key to continue</p>
            </div>
        </div>

        <div class="quickadd-rule"></div>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/quick-add/unlock" class="mb-2">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="quickadd-field">
                <label for="quick-add-key" class="quickadd-field-label">Quick Add key</label>
                <div class="quickadd-input-shell">
                    <svg class="quickadd-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="5.5"/><path d="M21 2l-9.6 9.6"/><path d="M15.5 7.5l3 3L22 7l-3-3"/></svg>
                    <input type="text" inputmode="text" autocapitalize="characters" autocomplete="off" spellcheck="false" id="quick-add-key" name="key" required class="quickadd-input" placeholder="XXXXX-XXXXX-XXXXX-XXXXX">
                </div>
            </div>
            <button type="submit" class="quickadd-cta">Unlock</button>
        </form>

        <p class="quickadd-footnote">Get this from your own device: <a href="/settings/profile">Settings &rarr; Profile &rarr; Quick Add key</a>. Prefer to sign in normally? <a href="/login">Log in</a>.</p>
    </div>
</body>
</html>
