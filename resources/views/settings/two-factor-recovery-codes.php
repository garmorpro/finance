<?php

/** @var list<string> $codes */
/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery codes · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Two-factor authentication is on</h1>

                <div class="alert-success">Save these recovery codes now — this is the only time they'll be shown.</div>

                <div class="card space-y-4">
                    <div>
                        <h2 class="font-medium text-stone-900 dark:text-white mb-1">Recovery codes</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400">Each code works once, as a way back into your account if you lose access to your authenticator app. Store them somewhere safe — a password manager, or printed and kept somewhere secure.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 rounded-lg bg-stone-50 dark:bg-stone-800 p-4 font-mono text-sm text-stone-900 dark:text-white select-all">
                        <?php foreach ($codes as $code): ?>
                            <div><?= View::e($code) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <a href="/settings/security" class="btn-primary inline-block">I've saved these codes</a>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
