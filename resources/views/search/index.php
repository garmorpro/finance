<?php

/** @var string $query */
/** @var array{transactions: array, transactionTotal: int, accounts: array, categories: array, tags: array}|null $results */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$hasAnyResults = $results !== null && (
    $results['transactions'] !== [] || $results['accounts'] !== [] || $results['categories'] !== [] || $results['tags'] !== []
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'search']); ?>

        <div class="app-content">
            <main class="page-main">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Search</h1>

                <form method="GET" action="/search" class="flex gap-3">
                    <input type="text" name="q" value="<?= View::e($query) ?>" placeholder="Search transactions, accounts, categories, tags&hellip;" autofocus class="field-input flex-1">
                    <button type="submit" class="btn-primary">Search</button>
                </form>

                <?php if ($query === ''): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">Search by payee, notes, account, category, or tag name.</p>
                    </div>
                <?php elseif (!$hasAnyResults): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">No results for "<?= View::e($query) ?>".</p>
                    </div>
                <?php else: ?>
                    <?php if ($results['transactions'] !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-2">Transactions</h2>
                            <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                                <?php foreach ($results['transactions'] as $transaction): ?>
                                    <a href="/transactions/<?= (int) $transaction['id'] ?>/edit" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800/50 -mx-2 px-2 rounded-lg transition-colors">
                                        <div class="min-w-0">
                                            <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($transaction['payee']) ?></div>
                                            <div class="text-sm text-stone-500 dark:text-stone-400"><?= View::e($transaction['transaction_date']) ?> &middot; <?= View::e($transaction['account_name']) ?><?= $transaction['category_name'] !== null ? ' · ' . View::e($transaction['category_name']) : '' ?></div>
                                        </div>
                                        <span class="font-medium text-right shrink-0 <?= $transaction['transaction_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($transaction['amount']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($results['transactionTotal'] > count($results['transactions'])): ?>
                                <a href="/transactions?search=<?= urlencode($query) ?>" class="inline-block mt-2 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all <?= $results['transactionTotal'] ?> matching transactions &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($results['accounts'] !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-2">Accounts</h2>
                            <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                                <?php foreach ($results['accounts'] as $account): ?>
                                    <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800/50 -mx-2 px-2 rounded-lg transition-colors">
                                        <div class="min-w-0">
                                            <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($account['name']) ?></div>
                                            <?php if (!empty($account['institution_name'])): ?>
                                                <div class="text-sm text-stone-500 dark:text-stone-400"><?= View::e($account['institution_name']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="font-medium text-stone-900 dark:text-white shrink-0"><?= Money::format($account['current_balance']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($results['categories'] !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-2">Categories</h2>
                            <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                                <?php foreach ($results['categories'] as $category): ?>
                                    <a href="/settings/categories" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800/50 -mx-2 px-2 rounded-lg transition-colors">
                                        <span class="font-medium text-stone-900 dark:text-white"><?= View::e($category['name']) ?></span>
                                        <span class="badge"><?= View::e(ucfirst($category['type'])) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($results['tags'] !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-2">Tags</h2>
                            <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                                <?php foreach ($results['tags'] as $tag): ?>
                                    <a href="/settings/tags" class="flex items-center py-3 first:pt-0 last:pb-0 hover:bg-stone-50 dark:hover:bg-stone-800/50 -mx-2 px-2 rounded-lg transition-colors">
                                        <span class="font-medium text-stone-900 dark:text-white"><?= View::e($tag['name']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
