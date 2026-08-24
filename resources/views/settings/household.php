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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
                            <div class="card">
                                <h3 class="font-medium text-stone-900 dark:text-white mb-1">Budget planning reminders</h3>
                                <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">
                                    Email every Owner and Administrator a link to review next month's budget before it starts.
                                    Each link is scoped to that one review and that one recipient — it's the only outbound email MyCFO+ sends.
                                </p>
                                <form method="POST" action="/settings/household/budget-reminder" class="space-y-4">
                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                                    <label class="flex items-start gap-3">
                                        <input type="checkbox" name="enabled" value="1" <?= !empty($household['budget_reminder_enabled']) ? 'checked' : '' ?> class="mt-1 rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                                        <span>
                                            <span class="block text-sm font-medium text-stone-900 dark:text-white">Email a review link before each month starts</span>
                                            <span class="block text-sm text-stone-500 dark:text-stone-400">Off by default. Turning this off stops future emails; links already sent keep working until they expire.</span>
                                        </span>
                                    </label>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label for="days_before" class="field-label">Send reminder</label>
                                            <?php $daysBefore = (int) ($household['budget_reminder_days_before'] ?? 5); ?>
                                            <select id="days_before" name="days_before" class="field-input">
                                                <?php foreach ([3, 5, 7, 10] as $option): ?>
                                                    <option value="<?= $option ?>" <?= $daysBefore === $option ? 'selected' : '' ?>><?= $option ?> days before month starts</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="link_single_use" class="field-label">Link can be used</label>
                                            <?php $singleUse = !isset($household['budget_reminder_link_single_use']) || (bool) $household['budget_reminder_link_single_use']; ?>
                                            <select id="link_single_use" name="link_single_use" class="field-input">
                                                <option value="1" <?= $singleUse ? 'selected' : '' ?>>Once</option>
                                                <option value="0" <?= !$singleUse ? 'selected' : '' ?>>Multiple times, until it expires</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="link_expiry_days" class="field-label">Link expires after</label>
                                            <?php $expiryDays = (int) ($household['budget_reminder_link_expiry_days'] ?? 7); ?>
                                            <select id="link_expiry_days" name="link_expiry_days" class="field-input">
                                                <?php foreach ([3, 7, 14, 30] as $option): ?>
                                                    <option value="<?= $option ?>" <?= $expiryDays === $option ? 'selected' : '' ?>><?= $option ?> days</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-primary">Save</button>
                                </form>
                            </div>

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
