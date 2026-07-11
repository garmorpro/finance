<?php

/** @var string $csrfToken */
/** @var string $active */

use App\Middleware\AuthMiddleware;
use App\Support\View;

$active = $active ?? '';

$link = function (string $key, string $href, string $label) use ($active): string {
    $class = $key === $active ? 'sidebar-link-active' : 'sidebar-link';
    return '<a href="' . $href . '" class="' . $class . '">' . $label . '</a>';
};

?>
<aside class="sidebar">
    <div class="sidebar-brand">Finance</div>

    <div class="flex items-center gap-1 px-4 pb-3">
        <span class="sidebar-icon-btn" aria-hidden="true" title="Coming soon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <span class="sidebar-icon-btn" aria-hidden="true" title="Coming soon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </span>
        <a href="/settings/categories" class="sidebar-icon-btn cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800" title="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
        <span class="sidebar-icon-btn ml-auto" aria-hidden="true" title="Coming soon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        </span>
    </div>

    <nav class="sidebar-nav">
        <?= $link('overview', '/', 'Overview') ?>
        <?= $link('accounts', '/accounts', 'Accounts') ?>
        <?= $link('transactions', '/transactions', 'Transactions') ?>

        <span class="sidebar-link-disabled" title="Coming soon">Cash Flow</span>
        <?= $link('budgets', '/budgets', 'Budgets') ?>
        <?= $link('recurring', '/recurring', 'Recurring') ?>
        <span class="sidebar-link-disabled" title="Coming soon">Goals</span>
        <span class="sidebar-link-disabled" title="Coming soon">Reports</span>

        <div class="pt-2 mt-2 border-t border-stone-100 dark:border-stone-800">
            <?= $link('household', '/household', 'Household') ?>
            <?= $link('settings', '/settings/categories', 'Settings') ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="/profile" class="sidebar-user block hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg py-2 <?= $active === 'profile' ? 'bg-stone-100 dark:bg-stone-800' : '' ?>">
            <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e(AuthMiddleware::userName()) ?></div>
            <div class="text-stone-500 dark:text-stone-400 text-xs truncate"><?= View::e(AuthMiddleware::userEmail()) ?></div>
        </a>
        <form method="POST" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn-secondary btn-block">Log out</button>
        </form>
    </div>
</aside>
