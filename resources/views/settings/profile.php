<?php

/** @var array $user */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'profile']); ?>

                    <div class="space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Profile</h2>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Your name and email address.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <div class="card max-w-lg">
                            <form method="POST" action="/settings/profile" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div>
                                    <label for="name" class="field-label">Name</label>
                                    <input type="text" id="name" name="name" value="<?= View::e($user['name']) ?>" required class="field-input">
                                </div>
                                <div>
                                    <label for="email" class="field-label">Email</label>
                                    <input type="email" id="email" name="email" value="<?= View::e($user['email']) ?>" required class="field-input">
                                </div>
                                <button type="submit" class="btn-primary">Save changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
