<?php

/** @var array $recentTransactions */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Recent Transactions', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<?php if ($recentTransactions === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No transactions yet.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($recentTransactions as $transaction): ?>
            <li class="flex items-center justify-between text-sm">
                <div class="truncate">
                    <div class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($transaction['payee']) ?></div>
                    <div class="text-xs text-stone-400 dark:text-stone-600"><?= View::e($transaction['transaction_date']) ?></div>
                </div>
                <span class="font-medium <?= $transaction['transaction_type'] === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                    <?= Money::format($transaction['amount']) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="/transactions" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">
        View all &rarr;
    </a>
<?php endif; ?>
