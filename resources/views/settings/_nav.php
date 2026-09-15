<?php

/** @var string $active */

use App\Middleware\AuthMiddleware;
use App\Support\SettingsIcons;
use App\Support\View;

$active = $active ?? '';

$navLink = function (string $key, string $href, string $label) use ($active): string {
    $class = $key === $active ? 'settings-nav-link-active' : 'settings-nav-link';
    return '<a href="' . $href . '" class="' . $class . '">' . SettingsIcons::svg($key) . '<span>' . $label . '</span></a>';
};

?>
<div class="card">
    <div class="settings-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" id="settings-nav-search" placeholder="Search settings" autocomplete="off" aria-label="Search settings">
    </div>

    <div id="settings-nav-list">
        <div class="settings-nav-group">
            <h2 class="settings-nav-heading">Account</h2>
            <nav class="space-y-0.5" aria-label="Account settings">
                <?= $navLink('profile', '/settings/profile', 'Profile') ?>
                <?= $navLink('notifications', '/settings/notifications', 'Notifications') ?>
                <?= $navLink('security', '/settings/security', 'Security') ?>
            </nav>
        </div>

        <div class="settings-nav-group">
            <h2 class="settings-nav-heading">Household</h2>
            <nav class="space-y-0.5" aria-label="Household settings">
                <?= $navLink('household', '/settings/household', 'Members') ?>
                <?= $navLink('categories', '/settings/categories', 'Categories') ?>
                <?= $navLink('rules', '/settings/rules', 'Rules') ?>
                <?= $navLink('tags', '/settings/tags', 'Tags') ?>
                <?php if (AuthMiddleware::role() === 'owner'): ?>
                    <?= $navLink('audit-log', '/settings/audit-log', 'Audit Log') ?>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <p id="settings-nav-empty" class="text-sm text-stone-400 dark:text-stone-600 text-center py-3 hidden">No matches</p>
</div>

<script src="<?= View::asset('/assets/js/settings-nav.js') ?>" defer></script>
