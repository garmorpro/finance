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

    <nav class="sidebar-nav">
        <?= $link('overview', '/', 'Overview') ?>
        <?= $link('accounts', '/accounts', 'Accounts') ?>

        <span class="sidebar-link-disabled" title="Coming soon">Transactions</span>
        <span class="sidebar-link-disabled" title="Coming soon">Cash Flow</span>
        <span class="sidebar-link-disabled" title="Coming soon">Budgets</span>
        <span class="sidebar-link-disabled" title="Coming soon">Recurring</span>
        <span class="sidebar-link-disabled" title="Coming soon">Goals</span>
        <span class="sidebar-link-disabled" title="Coming soon">Reports</span>

        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800">
            <?= $link('household', '/household', 'Household') ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="/profile" class="sidebar-user block hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg py-2 <?= $active === 'profile' ? 'bg-slate-100 dark:bg-slate-800' : '' ?>">
            <div class="font-medium text-slate-900 dark:text-white truncate"><?= View::e(AuthMiddleware::userName()) ?></div>
            <div class="text-slate-500 dark:text-slate-400 text-xs truncate"><?= View::e(AuthMiddleware::userEmail()) ?></div>
        </a>
        <form method="POST" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn-secondary btn-block">Log out</button>
        </form>
    </div>
</aside>
