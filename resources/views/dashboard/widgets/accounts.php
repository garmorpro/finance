<?php

/** @var array $accounts */

use App\Support\Money;
use App\Support\View;

$topAccounts = array_slice($accounts, 0, 4);

?>
<div class="tile-header">
    <span class="label-text mb-0">Accounts</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($accounts === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No accounts yet.</p>
        <a href="/accounts/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($topAccounts as $account): ?>
            <li class="flex items-center justify-between text-sm">
                <span class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($account['name']) ?></span>
                <span class="font-medium text-stone-900 dark:text-white"><?= Money::format($account['current_balance']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="/accounts" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">
        View all <?= count($accounts) ?> &rarr;
    </a>
<?php endif; ?>
