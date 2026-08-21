<?php

/**
 * Renders directly in the main column of dashboard/index.php, paired
 * with Insights — a fixed card, not a draggable/hideable registry tile.
 *
 * @var list<array<string, mixed>> $recentTransactions each row is a
 *     transactions.* row plus account_name, category_name, category_color
 */

use App\Support\Money;
use App\Support\View;

$today = gmdate('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$icons = [
    // Transfers get their own icon regardless of category (they're
    // never categorized); income/expense fall back to this when the
    // transaction has no category yet.
    'transfer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3l4 4-4 4"/><path d="M3 7h18"/><path d="M7 21l-4-4 4-4"/><path d="M21 17H3"/></svg>',
    'income' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>',
    'expense' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/></svg>',
];

?>
<div class="flex items-baseline justify-between flex-wrap gap-1 mb-4">
    <h2 class="text-base font-semibold text-stone-900 dark:text-white">Recent Transactions</h2>
    <p class="text-xs text-stone-500 dark:text-stone-400">Your latest activity across every account</p>
</div>

<?php if ($recentTransactions === []): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No transactions yet.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add your first transaction &rarr;</a>
    </div>
<?php else: ?>
    <div class="txn-list">
        <?php foreach ($recentTransactions as $txn): ?>
            <?php
            $isTransfer = $txn['transaction_type'] === 'transfer';
            $isIncome = $txn['transaction_type'] === 'income';
            $categoryColor = $txn['category_color'] ?? ($isTransfer ? '#78716c' : '#a8a29e');
            $icon = $isTransfer ? $icons['transfer'] : ($isIncome ? $icons['income'] : $icons['expense']);

            if ($txn['transaction_date'] === $today) {
                $dateLabel = 'Today';
            } elseif ($txn['transaction_date'] === $yesterday) {
                $dateLabel = 'Yesterday';
            } else {
                $dateLabel = date('M j', strtotime($txn['transaction_date']));
            }

            $categoryLabel = $isTransfer ? 'Transfer' : ($txn['category_name'] ?? 'Uncategorized');
            ?>
            <div class="txn-row">
                <span class="txn-icon" style="background:<?= View::e($categoryColor) ?>;">
                    <?= $icon ?>
                </span>
                <div class="txn-main">
                    <div class="txn-merchant"><?= View::e($txn['payee']) ?></div>
                    <div class="txn-meta"><?= View::e($categoryLabel) ?> &middot; <?= View::e($txn['account_name']) ?></div>
                </div>
                <div class="txn-right">
                    <div class="txn-amt tabular-nums <?= $isIncome ? 'txn-amt-positive' : '' ?>">
                        <?= $isIncome ? '+' : '' ?><?= Money::format($txn['amount']) ?>
                    </div>
                    <div class="txn-date"><?= View::e($dateLabel) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="txn-more">
        <a href="/transactions">View all transactions &rarr;</a>
    </div>
<?php endif; ?>
