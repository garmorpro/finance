<?php

/** @var array<string, array> $groups keyed by transaction_date, most recent first */
/** @var int $loadedCount */
/** @var array{total: int, before_today: int} $counts */
/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

$today = gmdate('Y-m-d');
$yesterday = gmdate('Y-m-d', strtotime('-1 day'));

$groupLabel = function (string $date) use ($today, $yesterday): string {
    if ($date === $today) {
        return 'Today';
    }
    if ($date === $yesterday) {
        return 'Yesterday';
    }
    return date('l, F j', strtotime($date));
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review transactions · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Review transactions</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                            <?= $counts['total'] ?> to review
                            <?php if ($counts['before_today'] > 0): ?>
                                <span class="text-amber-600 dark:text-amber-400 font-medium">&middot; <?= $counts['before_today'] ?> from before today</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <a href="/transactions" class="btn-secondary">Back to transactions</a>
                        <?php if ($counts['total'] > 0): ?>
                            <form method="POST" action="/transactions/review/mark-all" onsubmit="return confirm('Mark all <?= $counts['total'] ?> transactions as reviewed?');">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button type="submit" class="btn-primary">Mark all reviewed</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <?php if ($counts['total'] > $loadedCount): ?>
                    <div class="alert bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-900">
                        Showing the most recent <?= $loadedCount ?> of <?= $counts['total'] ?>. Review some of these to see the rest.
                    </div>
                <?php endif; ?>

                <?php if ($groups === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">You're all caught up — nothing left to review.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($groups as $date => $transactions): ?>
                        <div>
                            <h2 class="label-text mb-2"><?= View::e($groupLabel((string) $date)) ?></h2>
                            <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                                <?php foreach ($transactions as $transaction): ?>
                                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                        <div class="min-w-0">
                                            <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($transaction['payee']) ?></div>
                                            <div class="text-xs text-stone-500 dark:text-stone-400 truncate">
                                                <?= View::e($transaction['account_name']) ?>
                                                &middot;
                                                <?= View::e($transaction['transaction_type'] === 'transfer' ? 'Transfer' : ($transaction['category_name'] ?? 'Uncategorized')) ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 shrink-0">
                                            <span class="font-medium <?= $transaction['transaction_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                                                <?= Money::format($transaction['amount']) ?>
                                            </span>
                                            <a href="/transactions/<?= (int) $transaction['id'] ?>/edit" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Edit</a>
                                            <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>/review">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <button type="submit" class="btn-secondary">Mark reviewed</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
