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
    <title>Household · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'household']); ?>

        <div class="app-content">
        <main class="page-main">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white"><?= View::e($household['name'] ?? 'Household') ?></h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Members and pending invitations for your household.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="font-medium text-stone-900 dark:text-white mb-4">Members</h2>
            <table class="table-base">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td class="font-medium text-stone-900 dark:text-white"><?= View::e($member['name']) ?></td>
                            <td><?= View::e($member['email']) ?></td>
                            <td><span class="<?= $member['role'] === 'owner' ? 'badge-owner' : 'badge' ?>"><?= View::e(ucfirst($member['role'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($canManage): ?>
            <?php if ($pendingInvitations !== []): ?>
            <div class="card">
                <h2 class="font-medium text-stone-900 dark:text-white mb-4">Pending invitations</h2>
                <table class="table-base">
                    <thead>
                        <tr><th>Email</th><th>Role</th><th>Expires</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvitations as $invite): ?>
                            <tr>
                                <td><?= View::e($invite['email']) ?></td>
                                <td><span class="badge"><?= View::e(ucfirst($invite['role'])) ?></span></td>
                                <td class="text-stone-500 dark:text-stone-400"><?= View::e($invite['expires_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="font-medium text-stone-900 dark:text-white mb-4">Invite someone</h2>
                <form method="POST" action="/household/invite" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div>
                        <label for="email" class="field-label">Email</label>
                        <input type="email" id="email" name="email" required class="field-input">
                    </div>
                    <div>
                        <label for="role" class="field-label">Role</label>
                        <select id="role" name="role" class="field-input">
                            <option value="administrator">Administrator</option>
                            <option value="member" selected>Member</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Send invitation</button>
                </form>
            </div>
        <?php endif; ?>
        </main>
        </div>
    </div>
</body>
</html>
