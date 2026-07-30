<?php

/** @var array|null $household */
/** @var array $members */
/** @var array $pendingInvitations */
/** @var bool $canManage */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\SettingsIcons;
use App\Support\View;

$initialsFor = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false || $parts === []) {
        return '?';
    }
    return strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'household']); ?>

                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('household') ?></span>
                                <h2 class="text-lg font-semibold text-stone-900 dark:text-white"><?= View::e($household['name'] ?? 'Household') ?></h2>
                            </div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Members and pending invitations for your household.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <h3 class="font-medium text-stone-900 dark:text-white mb-4">Members</h3>
                            <table class="table-base">
                                <thead>
                                    <tr><th>Name</th><th>Email</th><th>Role</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td class="font-medium text-stone-900 dark:text-white">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-terracotta-600 text-white text-xs font-semibold flex-shrink-0"><?= View::e($initialsFor($member['name'])) ?></span>
                                                    <?= View::e($member['name']) ?>
                                                </div>
                                            </td>
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
                                <h3 class="font-medium text-stone-900 dark:text-white mb-4">Pending invitations</h3>
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
                                <h3 class="font-medium text-stone-900 dark:text-white mb-4">Invite someone</h3>
                                <form method="POST" action="/settings/household/invite" class="space-y-4">
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
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
