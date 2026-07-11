<?php

/** @var string $token */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        form { background: #1e293b; padding: 2rem; border-radius: 12px; width: 100%; max-width: 340px; }
        h1 { font-size: 1.25rem; margin: 0 0 1.5rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.25rem; color: #94a3b8; }
        input { width: 100%; padding: 0.5rem 0.75rem; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #f1f5f9; box-sizing: border-box; }
        button { width: 100%; padding: 0.6rem; border-radius: 6px; border: none; background: #3b82f6; color: white; font-weight: 600; cursor: pointer; }
        .error { background: #7f1d1d; color: #fecaca; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <form method="POST" action="/reset-password">
        <h1>Reset password</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="token" value="<?= View::e($token) ?>">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="12" autocomplete="new-password">
        <button type="submit">Reset password</button>
    </form>
</body>
</html>
