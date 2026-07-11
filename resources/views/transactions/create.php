<?php

/** @var array $accounts */
/** @var array $categories */
/** @var array $tags */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var array $old */

use App\Support\View;

$oldTagIds = array_map('intval', $old['tag_ids'] ?? []);
$oldIsSplit = !empty($old['is_split']);
$oldSplits = $old['splits'] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add transaction · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Add transaction</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/transactions" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="transaction_type" class="field-label">Type</label>
                            <select id="transaction_type" name="transaction_type" required class="field-input">
                                <option value="expense" <?= ($old['transaction_type'] ?? 'expense') === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="income" <?= ($old['transaction_type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="field-label">Amount</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" required value="<?= View::e($old['amount'] ?? '') ?>" class="field-input" placeholder="0.00">
                        </div>
                        <div>
                            <label for="account_id" class="field-label">Account</label>
                            <select id="account_id" name="account_id" required class="field-input">
                                <option value="">Select an account&hellip;</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === ($old['account_id'] ?? '') ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="transaction_date" class="field-label">Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" required value="<?= View::e($old['transaction_date'] ?? date('Y-m-d')) ?>" class="field-input">
                        </div>
                        <div class="col-span-2">
                            <label for="payee" class="field-label">Payee</label>
                            <input type="text" id="payee" name="payee" required value="<?= View::e($old['payee'] ?? '') ?>" class="field-input">
                        </div>
                        <div class="col-span-2">
                            <label for="category_id" class="field-label">Category (optional)</label>
                            <select id="category_id" name="category_id" class="field-input">
                                <option value="">No category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === ($old['category_id'] ?? '') ? 'selected' : '' ?>><?= View::e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-help">Ignored when split into multiple categories below.</p>
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="is_split" value="1" <?= $oldIsSplit ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Split this transaction across multiple categories
                        </label>
                        <div class="mt-3 space-y-2">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <?php $row = $oldSplits[$i] ?? ['category_id' => '', 'amount' => '']; ?>
                                <div class="grid grid-cols-[1fr_140px] gap-2">
                                    <select name="splits[<?= $i ?>][category_id]" class="field-input">
                                        <option value="">Choose category&hellip;</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === ($row['category_id'] ?? '') ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" inputmode="decimal" name="splits[<?= $i ?>][amount]" placeholder="0.00" value="<?= View::e($row['amount'] ?? '') ?>" class="field-input text-right">
                                </div>
                            <?php endfor; ?>
                            <p class="field-help">Up to 4 categories; amounts must add up to the total above.</p>
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($old['notes'] ?? '') ?></textarea>
                    </div>

                    <?php if ($tags !== []): ?>
                        <div>
                            <label for="tag_ids" class="field-label">Tags (optional)</label>
                            <select id="tag_ids" name="tag_ids[]" multiple class="field-input" size="4">
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= (int) $tag['id'] ?>" <?= in_array((int) $tag['id'], $oldTagIds, true) ? 'selected' : '' ?>><?= View::e($tag['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-help">Hold Cmd/Ctrl to select more than one.</p>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="is_reviewed" value="1" class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Reviewed
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="exclude_from_budget" value="1" class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Exclude from budget
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="exclude_from_reports" value="1" class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Exclude from reports
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Add transaction</button>
                        <a href="/transactions" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
