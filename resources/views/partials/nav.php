<?php

/** @var string $csrfToken */
/** @var string $active */

use App\Support\View;

?>
<header class="page-header">
    <a href="/" class="brand">Finance</a>
    <div class="flex items-center gap-6">
        <nav class="flex items-center gap-5">
            <a href="/" class="nav-link <?= ($active ?? '') === 'overview' ? 'text-slate-900 dark:text-white font-medium' : '' ?>">Overview</a>
            <a href="/accounts" class="nav-link <?= ($active ?? '') === 'accounts' ? 'text-slate-900 dark:text-white font-medium' : '' ?>">Accounts</a>
            <a href="/household" class="nav-link <?= ($active ?? '') === 'household' ? 'text-slate-900 dark:text-white font-medium' : '' ?>">Household</a>
            <a href="/profile" class="nav-link <?= ($active ?? '') === 'profile' ? 'text-slate-900 dark:text-white font-medium' : '' ?>">Profile</a>
        </nav>
        <form method="POST" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn-secondary">Log out</button>
        </form>
    </div>
</header>
