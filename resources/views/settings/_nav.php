<?php

/** @var string $active */

$active = $active ?? '';

$navLink = function (string $key, string $href, string $label) use ($active): string {
    $class = $key === $active ? 'settings-nav-link-active' : 'settings-nav-link';
    return '<a href="' . $href . '" class="' . $class . '">' . $label . '</a>';
};

?>
<div class="space-y-4">
    <div class="card">
        <h2 class="settings-nav-heading">Account</h2>
        <nav class="space-y-0.5">
            <?= $navLink('profile', '/settings/profile', 'Profile') ?>
            <?= $navLink('security', '/settings/security', 'Security') ?>
        </nav>
    </div>
    <div class="card">
        <h2 class="settings-nav-heading">Household</h2>
        <nav class="space-y-0.5">
            <?= $navLink('household', '/settings/household', 'Members') ?>
            <?= $navLink('categories', '/settings/categories', 'Categories') ?>
        </nav>
    </div>
</div>
