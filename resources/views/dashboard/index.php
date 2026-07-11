<?php

/** @var array $user */
/** @var array|null $household */
/** @var string|null $role */
/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; border-bottom: 1px solid #1e293b; }
        main { padding: 2rem; max-width: 640px; margin: 0 auto; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem; }
        .label { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { font-size: 1.1rem; margin-top: 0.25rem; }
        button { padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid #334155; background: transparent; color: #f1f5f9; cursor: pointer; }
        button:hover { background: #1e293b; }
    </style>
</head>
<body>
    <header>
        <strong>Finance</strong>
        <form method="POST" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit">Log out</button>
        </form>
    </header>
    <main>
        <div class="card">
            <div class="label">Logged in as</div>
            <div class="value"><?= View::e($user['name']) ?> (<?= View::e($user['email']) ?>)</div>
        </div>
        <?php if ($household !== null): ?>
        <div class="card">
            <div class="label">Household</div>
            <div class="value"><?= View::e($household['name']) ?></div>
            <div class="label" style="margin-top: 0.75rem;">Role</div>
            <div class="value"><?= View::e(ucfirst((string) $role)) ?></div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
