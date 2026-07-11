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
    <title>Profile</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; border-bottom: 1px solid #1e293b; }
        header a { color: #f1f5f9; text-decoration: none; }
        main { padding: 2rem; max-width: 480px; margin: 0 auto; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem; }
        h2 { font-size: 1rem; margin: 0 0 1rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.25rem; color: #94a3b8; }
        input { width: 100%; padding: 0.5rem 0.75rem; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #f1f5f9; box-sizing: border-box; }
        button { padding: 0.5rem 1rem; border-radius: 6px; border: none; background: #3b82f6; color: white; font-weight: 600; cursor: pointer; }
        .error { background: #7f1d1d; color: #fecaca; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .notice { background: #14532d; color: #bbf7d0; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <header>
        <a href="/">&larr; Back to overview</a>
        <strong>Profile</strong>
    </header>
    <main>
        <?php if (!empty($error)): ?>
            <div class="error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="notice"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Your info</h2>
            <form method="POST" action="/profile">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= View::e($user['name']) ?>" required>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= View::e($user['email']) ?>" required>
                <button type="submit">Save changes</button>
            </form>
        </div>

        <div class="card">
            <h2>Change password</h2>
            <form method="POST" action="/profile/password">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">
                <label for="new_password_confirmation">Confirm new password</label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="12" autocomplete="new-password">
                <button type="submit">Update password</button>
            </form>
        </div>
    </main>
</body>
</html>
