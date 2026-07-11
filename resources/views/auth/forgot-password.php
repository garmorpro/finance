<?php

/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        form { background: #1e293b; padding: 2rem; border-radius: 12px; width: 100%; max-width: 340px; }
        h1 { font-size: 1.25rem; margin: 0 0 1.5rem; }
        p.help { color: #94a3b8; font-size: 0.875rem; margin-top: -1rem; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.25rem; color: #94a3b8; }
        input { width: 100%; padding: 0.5rem 0.75rem; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #f1f5f9; box-sizing: border-box; }
        button { width: 100%; padding: 0.6rem; border-radius: 6px; border: none; background: #3b82f6; color: white; font-weight: 600; cursor: pointer; }
        .notice { background: #14532d; color: #bbf7d0; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        a { color: #3b82f6; font-size: 0.875rem; }
    </style>
</head>
<body>
    <form method="POST" action="/forgot-password">
        <h1>Forgot password</h1>
        <p class="help">Enter your account email and we'll generate a reset link.</p>
        <?php if (!empty($notice)): ?>
            <div class="notice"><?= View::e($notice) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="username">
        <button type="submit">Send reset link</button>
        <p style="margin-top: 1rem;"><a href="/login">Back to login</a></p>
    </form>
</body>
</html>
