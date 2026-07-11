<?php

/** @var array $transactions */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array $accounts */
/** @var array $categories */
/** @var array $filters */
/** @var array $tagsByTransaction */
/** @var int $unreviewedCount */
/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

$totalPages = max(1, (int) ceil($total / $perPage));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Transactions</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1"><?= $total ?> total</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="/transactions/review" class="btn-secondary">
                            Review
                            <?php if ($unreviewedCount > 0): ?>
                                <span class="badge-owner"><?= $unreviewedCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/transactions/export<?= $filters !== [] ? '?' . http_build_query($filters) : '' ?>" class="btn-secondary">Export</a>
                        <a href="/transactions/import" class="btn-secondary">Import</a>
                        <a href="/transactions/transfer" class="btn-secondary">Transfer</a>
                        <a href="/transactions/create" class="btn-primary">Add transaction</a>
                    </div>
                </div>

                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <form method="GET" action="/transactions" class="card">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label for="f-account" class="field-label">Account</label>
                            <select id="f-account" name="account_id" class="field-input">
                                <option value="">All</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === $filters['account_id'] ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="f-category" class="field-label">Category</label>
                            <select id="f-category" name="category_id" class="field-input">
                                <option value="">All</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $filters['category_id'] ? 'selected' : '' ?>><?= View::e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="f-type" class="field-label">Type</label>
                            <select id="f-type" name="type" class="field-input">
                                <option value="">All</option>
                                <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="transfer" <?= $filters['type'] === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label for="f-reviewed" class="field-label">Status</label>
                            <select id="f-reviewed" name="reviewed" class="field-input">
                                <option value="">All</option>
                                <option value="1" <?= $filters['reviewed'] === '1' ? 'selected' : '' ?>>Reviewed</option>
                                <option value="0" <?= $filters['reviewed'] === '0' ? 'selected' : '' ?>>Unreviewed</option>
                            </select>
                        </div>
                        <div>
                            <label for="f-from" class="field-label">From</label>
                            <input type="date" id="f-from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="f-to" class="field-label">To</label>
                            <input type="date" id="f-to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input">
                        </div>
                    </div>
                    <div class="flex items-end gap-3 mt-3">
                        <div class="flex-1">
                            <label for="f-search" class="field-label">Search payee or notes</label>
                            <input type="text" id="f-search" name="search" value="<?= View::e($filters['search']) ?>" class="field-input">
                        </div>
                        <button type="submit" class="btn-secondary">Filter</button>
                        <a href="/transactions" class="btn-secondary">Clear</a>
                    </div>
                </form>

                <?php if ($transactions === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">No transactions match.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <table class="table-base">
                            <thead>
                                <tr><th>Date</th><th>Payee</th><th>Category</th><th>Account</th><th class="text-right">Amount</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td class="text-stone-500 dark:text-stone-400"><?= View::e($transaction['transaction_date']) ?></td>
                                        <td class="font-medium text-stone-900 dark:text-white">
                                            <?= View::e($transaction['payee']) ?>
                                            <?php if ((int) $transaction['is_reviewed'] === 0): ?>
                                                <span class="badge ml-2">Unreviewed</span>
                                            <?php endif; ?>
                                            <?php foreach ($tagsByTransaction[(int) $transaction['id']] ?? [] as $tag): ?>
                                                <span class="badge ml-2"><?= View::e($tag['name']) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="text-stone-500 dark:text-stone-400"><?= View::e($transaction['transaction_type'] === 'transfer' ? 'Transfer' : ($transaction['category_name'] ?? '—')) ?></td>
                                        <td class="text-stone-500 dark:text-stone-400"><?= View::e($transaction['account_name']) ?></td>
                                        <td class="text-right font-medium <?= $transaction['transaction_type'] === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                                            <?= Money::format($transaction['amount']) ?>
                                        </td>
                                        <td class="text-right">
                                            <a href="/transactions/<?= (int) $transaction['id'] ?>/edit" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="flex items-center justify-between text-sm text-stone-500 dark:text-stone-400">
                        <span>Page <?= $page ?> of <?= $totalPages ?></span>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query([...$filters, 'page' => $page - 1]) ?>" class="btn-secondary">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query([...$filters, 'page' => $page + 1]) ?>" class="btn-secondary">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
