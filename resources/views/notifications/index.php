<?php

/** @var list<array{type: string, message: string, count: int, href: string}> $notifications */
/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => '']); ?>

        <div class="app-content">
            <main class="page-main">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Notifications</h1>
                    <a href="/settings/notifications" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Preferences</a>
                </div>

                <?php if ($notifications === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">You're all caught up.</p>
                    </div>
                <?php else: ?>
                    <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                        <?php foreach ($notifications as $notification): ?>
                            <a href="<?= View::e($notification['href']) ?>" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800/50 -mx-2 px-2 rounded-lg transition-colors">
                                <span class="text-sm text-stone-700 dark:text-stone-300"><?= View::e($notification['message']) ?></span>
                                <span class="badge-owner shrink-0"><?= $notification['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
