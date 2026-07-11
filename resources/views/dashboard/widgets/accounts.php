<?php

/** @var array $accounts */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$topAccounts = array_slice($accounts, 0, 4);

View::partial('dashboard/widgets/_header', ['title' => 'Accounts', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
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
