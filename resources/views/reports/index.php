<?php

/** @var list<array{label: string, income: string, expense: string, transfer: string, net: string, count: int}> $rows */
/** @var array{income: string, expense: string, transfer: string, net: string, count: int} $totals */
/** @var string $groupBy */
/** @var array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, type: string, user_id: int|null} $filters */
/** @var array $accounts */
/** @var array $categories */
/** @var array $members */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$groupLabels = [
    'category' => 'Category',
    'merchant' => 'Merchant',
    'account' => 'Account',
    'month' => 'Month',
    'member' => 'Household member',
];

$exportQuery = http_build_query([
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'account_ids' => $filters['account_ids'],
    'category_ids' => $filters['category_ids'],
    'type' => $filters['type'],
    'user_id' => $filters['user_id'] ?? '',
    'group_by' => $groupBy,
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'reports']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Reports</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Filter and group your transactions any way you need.</p>
                    </div>
                    <a href="/reports/export?<?= $exportQuery ?>" class="btn-secondary">Export CSV</a>
                </div>

                <form method="GET" action="/reports" class="card space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label for="date_from" class="field-label">From</label>
                            <input type="date" id="date_from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="date_to" class="field-label">To</label>
                            <input type="date" id="date_to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="type" class="field-label">Type</label>
                            <select id="type" name="type" class="field-input">
                                <option value="" <?= $filters['type'] === '' ? 'selected' : '' ?>>All</option>
                                <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="transfer" <?= $filters['type'] === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label for="user_id" class="field-label">Member</label>
                            <select id="user_id" name="user_id" class="field-input">
                                <option value="">Everyone</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= (int) $member['id'] ?>" <?= $filters['user_id'] === (int) $member['id'] ? 'selected' : '' ?>><?= View::e($member['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_by" class="field-label">Group by</label>
                            <select id="group_by" name="group_by" class="field-input">
                                <?php foreach ($groupLabels as $key => $label): ?>
                                    <option value="<?= View::e($key) ?>" <?= $groupBy === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary w-full">Apply</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="account_ids" class="field-label">Accounts (leave empty for all)</label>
                            <select id="account_ids" name="account_ids[]" multiple class="field-input" size="4">
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= in_array((int) $account['id'], $filters['account_ids'], true) ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="category_ids" class="field-label">Categories (leave empty for all)</label>
                            <select id="category_ids" name="category_ids[]" multiple class="field-input" size="4">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $filters['category_ids'], true) ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="card">
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-6">
                        <div>
                            <div class="label-text">Income</div>
                            <div class="text-xl font-semibold text-emerald-600 dark:text-emerald-400"><?= Money::format($totals['income']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Expenses</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totals['expense']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Transfers</div>
                            <div class="text-xl font-semibold text-stone-500 dark:text-stone-400"><?= Money::format($totals['transfer']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Net</div>
                            <div class="text-xl font-semibold <?= bccomp($totals['net'], '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= Money::format($totals['net']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Transactions</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $totals['count'] ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($rows === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">No transactions match these filters.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th><?= View::e($groupLabels[$groupBy]) ?></th>
                                    <th class="text-right">Income</th>
                                    <th class="text-right">Expenses</th>
                                    <th class="text-right">Transfers</th>
                                    <th class="text-right">Net</th>
                                    <th class="text-right">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="font-medium text-stone-900 dark:text-white"><?= View::e($row['label']) ?></td>
                                        <td class="text-right text-emerald-600 dark:text-emerald-400"><?= Money::format($row['income']) ?></td>
                                        <td class="text-right"><?= Money::format($row['expense']) ?></td>
                                        <td class="text-right text-stone-500 dark:text-stone-400"><?= Money::format($row['transfer']) ?></td>
                                        <td class="text-right font-medium <?= bccomp($row['net'], '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($row['net']) ?></td>
                                        <td class="text-right text-stone-500 dark:text-stone-400"><?= $row['count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
