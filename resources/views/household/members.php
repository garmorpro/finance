<?php

/** @var array|null $household */
/** @var array $members */
/** @var array $pendingInvitations */
/** @var bool $canManage */
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
    <title>Household</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; border-bottom: 1px solid #1e293b; }
        header a { color: #f1f5f9; text-decoration: none; }
        main { padding: 2rem; max-width: 640px; margin: 0 auto; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem; }
        h2 { font-size: 1rem; margin: 0 0 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.5rem 0; border-bottom: 1px solid #334155; font-size: 0.9rem; }
        th { color: #94a3b8; font-weight: 500; font-size: 0.75rem; text-transform: uppercase; }
        .role-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; background: #334155; font-size: 0.75rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.25rem; color: #94a3b8; }
        input, select { width: 100%; padding: 0.5rem 0.75rem; margin-bottom: 1rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #f1f5f9; box-sizing: border-box; }
        button { padding: 0.5rem 1rem; border-radius: 6px; border: none; background: #3b82f6; color: white; font-weight: 600; cursor: pointer; }
        .error { background: #7f1d1d; color: #fecaca; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
        .notice { background: #14532d; color: #bbf7d0; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <header>
        <a href="/">&larr; Back to overview</a>
        <strong><?= View::e($household['name'] ?? 'Household') ?></strong>
    </header>
    <main>
        <?php if (!empty($error)): ?>
            <div class="error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="notice"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Members</h2>
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= View::e($member['name']) ?></td>
                            <td><?= View::e($member['email']) ?></td>
                            <td><span class="role-badge"><?= View::e(ucfirst($member['role'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($canManage): ?>
            <?php if ($pendingInvitations !== []): ?>
            <div class="card">
                <h2>Pending invitations</h2>
                <table>
                    <thead>
                        <tr><th>Email</th><th>Role</th><th>Expires</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvitations as $invite): ?>
                            <tr>
                                <td><?= View::e($invite['email']) ?></td>
                                <td><span class="role-badge"><?= View::e(ucfirst($invite['role'])) ?></span></td>
                                <td><?= View::e($invite['expires_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Invite someone</h2>
                <form method="POST" action="/household/invite">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="administrator">Administrator</option>
                        <option value="member" selected>Member</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    <button type="submit">Send invitation</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
