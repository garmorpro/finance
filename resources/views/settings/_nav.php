<?php

/** @var string $active */

use App\Support\SettingsIcons;

$active = $active ?? '';

$navLink = function (string $key, string $href, string $label) use ($active): string {
    $class = $key === $active ? 'settings-nav-link-active' : 'settings-nav-link';
    return '<a href="' . $href . '" class="' . $class . '">' . SettingsIcons::svg($key) . '<span>' . $label . '</span></a>';
};

?>
<div class="card">
    <h2 class="settings-nav-heading">Account</h2>
    <nav class="space-y-0.5" aria-label="Account settings">
        <?= $navLink('profile', '/settings/profile', 'Profile') ?>
        <?= $navLink('notifications', '/settings/notifications', 'Notifications') ?>
        <?= $navLink('security', '/settings/security', 'Security') ?>
    </nav>

    <h2 class="settings-nav-heading mt-5">Household</h2>
    <nav class="space-y-0.5" aria-label="Household settings">
        <?= $navLink('household', '/settings/household', 'Members') ?>
        <?= $navLink('categories', '/settings/categories', 'Categories') ?>
        <?= $navLink('rules', '/settings/rules', 'Rules') ?>
        <?= $navLink('tags', '/settings/tags', 'Tags') ?>
    </nav>
</div>
