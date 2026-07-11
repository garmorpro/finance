<?php

/** @var array $transaction */
/** @var array $accounts */
/** @var array $categories */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

$absoluteAmount = ltrim($transaction['amount'], '-');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit transaction · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Edit transaction</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="transaction_type" class="field-label">Type</label>
                            <select id="transaction_type" name="transaction_type" required class="field-input">
                                <option value="expense" <?= $transaction['transaction_type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="income" <?= $transaction['transaction_type'] === 'income' ? 'selected' : '' ?>>Income</option>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="field-label">Amount</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" required value="<?= View::e($absoluteAmount) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="account_id" class="field-label">Account</label>
                            <select id="account_id" name="account_id" required class="field-input">
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === (int) $transaction['account_id'] ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="transaction_date" class="field-label">Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" required value="<?= View::e($transaction['transaction_date']) ?>" class="field-input">
                        </div>
                        <div class="col-span-2">
                            <label for="payee" class="field-label">Payee</label>
                            <input type="text" id="payee" name="payee" required value="<?= View::e($transaction['payee']) ?>" class="field-input">
                        </div>
                        <div class="col-span-2">
                            <label for="category_id" class="field-label">Category (optional)</label>
                            <select id="category_id" name="category_id" class="field-input">
                                <option value="">No category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($transaction['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= View::e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($transaction['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="is_reviewed" value="1" <?= (int) $transaction['is_reviewed'] === 1 ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Reviewed
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="exclude_from_budget" value="1" <?= (int) $transaction['exclude_from_budget'] === 1 ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Exclude from budget
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="exclude_from_reports" value="1" <?= (int) $transaction['exclude_from_reports'] === 1 ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Exclude from reports
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">Save changes</button>
                </form>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-2">Delete transaction</h2>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">This also reverses its effect on the account balance.</p>
                    <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>/delete" onsubmit="return confirm('Delete this transaction?');">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <button type="submit" class="btn-secondary">Delete</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
