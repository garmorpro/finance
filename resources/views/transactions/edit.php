<?php

/** @var array $transaction */
/** @var array $accounts */
/** @var array $categories */
/** @var array $tags */
/** @var list<int> $selectedTagIds */
/** @var array $existingSplits */
/** @var array $attachments */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

$absoluteAmount = ltrim($transaction['amount'], '-');
$isSplit = (int) $transaction['is_split'] === 1;
$splitRows = array_map(fn (array $split): array => [
    'category_id' => (string) $split['category_id'],
    'amount' => ltrim($split['amount'], '-'),
], $existingSplits);

$formatFileSize = function (int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    return round($bytes / 1024) . ' KB';
};

$transactionAccount = null;
foreach ($accounts as $account) {
    if ((int) $account['id'] === (int) $transaction['account_id']) {
        $transactionAccount = $account;
        break;
    }
}
$accountColor = ($transactionAccount['color'] ?? null) ?: '#a8a29e';
$isIncome = $transaction['transaction_type'] === 'income';
$typeIcon = $isIncome
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit transaction · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="account-card" style="background-color: <?= View::e($accountColor) ?>;">
                    <div class="flex items-start justify-between">
                        <span class="account-card-icon"><?= $typeIcon ?></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-white/70"><?= $isIncome ? 'Income' : 'Expense' ?></span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-lg font-semibold truncate"><?= View::e($transaction['payee']) ?></div>
                        <?php if ($transactionAccount !== null): ?>
                            <div class="text-sm text-white/70 truncate"><?= View::e($transactionAccount['name']) ?> &middot; <?= View::e($transaction['transaction_date']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-end justify-between mt-2">
                        <span class="text-lg tracking-widest text-white/60" aria-hidden="true">&bull;&bull;&bull;&bull;</span>
                        <div class="text-right">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-white/60">Amount</div>
                            <div class="text-2xl font-semibold"><?= Money::format($absoluteAmount) ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>" class="card space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-3">Basic info</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            <div class="sm:col-span-2">
                                <label for="payee" class="field-label">Payee</label>
                                <input type="text" id="payee" name="payee" required value="<?= View::e($transaction['payee']) ?>" class="field-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="category_id" class="field-label">Category (optional)</label>
                                <select id="category_id" name="category_id" class="field-input">
                                    <option value="">No category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (int) ($transaction['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= View::e($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field-help">Ignored when split into multiple categories below. When split, this shows the largest split's category.</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6">
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" name="is_split" value="1" <?= $isSplit ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                            Split this transaction across multiple categories
                        </label>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <?php $row = $splitRows[$i] ?? ['category_id' => '', 'amount' => '']; ?>
                                <div class="grid grid-cols-[1fr_140px] gap-2">
                                    <select name="splits[<?= $i ?>][category_id]" class="field-input">
                                        <option value="">Choose category&hellip;</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $row['category_id'] ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" inputmode="decimal" name="splits[<?= $i ?>][amount]" placeholder="0.00" value="<?= View::e($row['amount']) ?>" class="field-input text-right">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <p class="field-help mt-2">Up to 4 categories; amounts must add up to the total above.</p>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="notes" class="field-label">Notes (optional)</label>
                            <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($transaction['notes'] ?? '') ?></textarea>
                        </div>

                        <?php if ($tags !== []): ?>
                            <div>
                                <div class="field-label">Tags (optional)</div>
                                <?php View::partial('partials/checkbox-group', [
                                    'name' => 'tag_ids',
                                    'options' => array_map(fn (array $tag): array => [
                                        'value' => (int) $tag['id'],
                                        'label' => $tag['name'],
                                        'checked' => in_array((int) $tag['id'], $selectedTagIds, true),
                                    ], $tags),
                                ]); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6 flex items-center gap-6">
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
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Attachments</h2>

                    <?php if ($attachments === []): ?>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">No receipts or files attached yet.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-stone-100 dark:divide-stone-800 mb-4">
                            <?php foreach ($attachments as $attachment): ?>
                                <li class="flex items-center justify-between gap-4 py-2.5">
                                    <a href="/transactions/<?= (int) $transaction['id'] ?>/attachments/<?= (int) $attachment['id'] ?>" target="_blank" rel="noopener" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline truncate">
                                        <?= View::e($attachment['original_filename']) ?>
                                    </a>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="text-xs text-stone-500 dark:text-stone-400"><?= $formatFileSize((int) $attachment['file_size']) ?></span>
                                        <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>/attachments/<?= (int) $attachment['id'] ?>/delete" onsubmit="return confirm('Remove this attachment?');">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button type="submit" class="text-xs font-medium text-stone-500 dark:text-stone-400 hover:text-red-600 dark:hover:text-red-400">Remove</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>/attachments" enctype="multipart/form-data" class="flex items-center gap-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" required class="field-input">
                        <button type="submit" class="btn-secondary shrink-0">Upload</button>
                    </form>
                    <p class="field-help">JPG, PNG, WEBP, or PDF, up to 10MB.</p>
                </div>

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
