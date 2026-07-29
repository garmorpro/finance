<?php

/** @var array $transactions */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array{income: string, expenses: string, net: string} $sums */
/** @var string $sort */
/** @var string $dir */
/** @var array $accounts */
/** @var array $categories */
/** @var array $filters */
/** @var array $tagsByTransaction */
/** @var array $attachmentsByTransaction */
/** @var array $tags */
/** @var string $csrfToken */
/** @var string|null $notice */
/** @var string|null $error */

use App\Support\Money;
use App\Support\View;

$totalPages = max(1, (int) ceil($total / $perPage));
$returnQuery = http_build_query([...$filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page]);

/**
 * Builds a column-header link that toggles sort direction when the same
 * column is clicked again, preserves every active filter, and resets back
 * to page 1 (a sort change on page 3 shouldn't leave the user staring at
 * a now-unrelated slice of results).
 */
$sortLink = function (string $column) use ($filters, $sort, $dir): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

    return '?' . http_build_query([...$filters, 'sort' => $column, 'dir' => $nextDir, 'page' => 1]);
};

$sortIndicator = function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '';
    }

    return $dir === 'asc' ? ' &uarr;' : ' &darr;';
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Transactions</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1"><?= $total ?> total</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="/transactions/export<?= '?' . http_build_query($filters) ?>" class="btn-secondary">Export</a>
                        <a href="/transactions/import" class="btn-secondary">Import</a>
                        <a href="/transactions/transfer" class="btn-secondary">Transfer</a>
                        <a href="/transactions/create" class="btn-primary">Add transaction</a>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <div class="label-text">Income</div>
                            <div class="text-xl font-semibold text-emerald-700 dark:text-emerald-400"><?= Money::format($sums['income']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Expenses</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($sums['expenses']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Net</div>
                            <div class="text-xl font-semibold <?= bccomp($sums['net'], '0.00', 2) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= Money::format($sums['net']) ?></div>
                        </div>
                    </div>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-3">Reflects the filters below, not just the current page. Transfers aren't counted as income or spending.</p>
                </div>

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
                        <?php if ($tags !== []): ?>
                        <div>
                            <label for="f-tag" class="field-label">Tag</label>
                            <select id="f-tag" name="tag_id" class="field-input">
                                <option value="">All</option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= (int) $tag['id'] ?>" <?= (string) $tag['id'] === $filters['tag_id'] ? 'selected' : '' ?>><?= View::e($tag['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label for="f-from" class="field-label">From</label>
                            <input type="date" id="f-from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="f-to" class="field-label">To</label>
                            <input type="date" id="f-to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input">
                        </div>
                    </div>
                    <div class="flex items-end flex-wrap gap-3 mt-3">
                        <div class="flex-1 min-w-[12rem]">
                            <label for="f-search" class="field-label">Search payee or notes</label>
                            <input type="text" id="f-search" name="search" value="<?= View::e($filters['search']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="f-amount-min" class="field-label">Min amount</label>
                            <input type="number" step="0.01" min="0" id="f-amount-min" name="amount_min" value="<?= View::e($filters['amount_min']) ?>" class="field-input" style="max-width: 8rem;">
                        </div>
                        <div>
                            <label for="f-amount-max" class="field-label">Max amount</label>
                            <input type="number" step="0.01" min="0" id="f-amount-max" name="amount_max" value="<?= View::e($filters['amount_max']) ?>" class="field-input" style="max-width: 8rem;">
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
                    <form method="POST" action="/transactions/bulk" id="bulk-form">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input type="hidden" name="return_query" value="<?= View::e($returnQuery) ?>">

                        <div class="card">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="w-8"><input type="checkbox" id="select-all-checkbox" title="Select all on this page" class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500"></th>
                                        <th><a href="<?= View::e($sortLink('date')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Date<?= $sortIndicator('date') ?></a></th>
                                        <th><a href="<?= View::e($sortLink('payee')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Payee<?= $sortIndicator('payee') ?></a></th>
                                        <th>Category</th>
                                        <th>Account</th>
                                        <th class="text-right"><a href="<?= View::e($sortLink('amount')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Amount<?= $sortIndicator('amount') ?></a></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php $categoryColor = $transaction['category_color'] ?? null; ?>
                                        <tr class="row-clickable cursor-pointer hover:bg-stone-50 dark:hover:bg-stone-800/60 transition-colors" data-href="/transactions/<?= (int) $transaction['id'] ?>/edit">
                                            <td>
                                                <input type="checkbox" name="transaction_ids[]" value="<?= (int) $transaction['id'] ?>" class="row-checkbox rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                                            </td>
                                            <td class="text-stone-500 dark:text-stone-400 whitespace-nowrap"><?= View::e($transaction['transaction_date']) ?></td>
                                            <td class="font-medium text-stone-900 dark:text-white">
                                                <?= View::e($transaction['payee']) ?>
                                                <?php foreach ($tagsByTransaction[(int) $transaction['id']] ?? [] as $tag): ?>
                                                    <span class="badge ml-2"><?= View::e($tag['name']) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (($attachmentsByTransaction[(int) $transaction['id']] ?? []) !== []): ?>
                                                    <span class="inline-block align-middle ml-2 text-stone-500 dark:text-stone-400" title="Has attachments">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Has attachments"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-stone-500 dark:text-stone-400">
                                                <?php if ($transaction['transaction_type'] === 'transfer'): ?>
                                                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2 h-2 rounded-full bg-stone-400 flex-shrink-0"></span>Transfer</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($categoryColor ?: '#a8a29e') ?>;"></span>
                                                        <?= View::e($transaction['category_name'] ?? '—') ?>
                                                    </span>
                                                    <?php if ((int) $transaction['is_split'] === 1): ?>
                                                        <span class="badge ml-1" title="Split across multiple categories">Split</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-stone-500 dark:text-stone-400"><?= View::e($transaction['account_name']) ?></td>
                                            <td class="text-right font-medium whitespace-nowrap <?= $transaction['transaction_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
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

                        <div class="card flex flex-wrap items-end gap-3">
                            <span class="text-sm font-medium text-stone-500 dark:text-stone-400 pb-2">Bulk actions:</span>
                            <div>
                                <select name="category_id" class="field-input" style="max-width: 12rem;">
                                    <option value="">Choose category&hellip;</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>"><?= View::e($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="action" value="set_category" class="btn-secondary">Set category</button>

                            <?php if ($tags !== []): ?>
                                <div>
                                    <select name="tag_id" class="field-input" style="max-width: 12rem;">
                                        <option value="">Choose tag&hellip;</option>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?= (int) $tag['id'] ?>"><?= View::e($tag['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="action" value="add_tag" class="btn-secondary">Add tag</button>
                            <?php endif; ?>

                            <button type="submit" name="action" value="delete" id="bulk-delete-btn" class="btn-secondary ml-auto">Delete</button>
                        </div>
                    </form>

                    <?php if ($totalPages > 1): ?>
                    <div class="flex items-center justify-between text-sm text-stone-500 dark:text-stone-400">
                        <span>Page <?= $page ?> of <?= $totalPages ?></span>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query([...$filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page - 1]) ?>" class="btn-secondary">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query([...$filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page + 1]) ?>" class="btn-secondary">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('/assets/js/transactions.js') ?>" defer></script>
</body>
</html>
