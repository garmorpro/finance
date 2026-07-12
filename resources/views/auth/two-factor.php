<?php

/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify it's you · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card">
        <h1 class="text-xl font-semibold text-stone-900 dark:text-white mb-2">Verify it's you</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400 mb-6">Enter the 6-digit code from your authenticator app.</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login/verify" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div>
                <label for="code" class="field-label">Authentication code</label>
                <input type="text" id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" autofocus class="field-input" placeholder="123456">
                <p class="field-help">Lost your device? Enter a recovery code instead (format: XXXX-XXXX).</p>
            </div>
            <button type="submit" class="btn-primary btn-block">Verify</button>
        </form>
    </div>
</body>
</html>
