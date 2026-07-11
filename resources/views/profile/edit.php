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
    <title>Profile · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'profile']); ?>

        <div class="app-content">
        <main class="page-main">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Profile</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Manage your account details and password.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="font-medium text-stone-900 dark:text-white mb-4">Your info</h2>
            <form method="POST" action="/profile" class="space-y-4">
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

        <div class="card">
            <h2 class="font-medium text-stone-900 dark:text-white mb-4">Change password</h2>
            <form method="POST" action="/profile/password" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div>
                    <label for="current_password" class="field-label">Current password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password" class="field-input">
                </div>
                <div>
                    <label for="new_password" class="field-label">New password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password" class="field-input">
                    <p class="field-help">At least 12 characters.</p>
                </div>
                <div>
                    <label for="new_password_confirmation" class="field-label">Confirm new password</label>
                    <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="12" autocomplete="new-password" class="field-input">
                </div>
                <button type="submit" class="btn-primary">Update password</button>
            </form>
        </div>
        </main>
        </div>
    </div>
</body>
</html>
