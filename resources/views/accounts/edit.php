<?php

/** @var array $account */
/** @var array $history */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $types */

use App\Support\AccountTypeIcons;
use App\Support\AccountTypeLabels;
use App\Support\Money;
use App\Support\View;

$accountColor = $account['color'] ?: '#a8a29e';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= View::e($account['name']) ?> · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

        <div class="app-content">
        <main class="page-main-wide">
        <a href="/accounts" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Accounts</a>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <!-- Hero -->
        <div class="stat-hero stat-hero-neutral flex items-start justify-between flex-wrap gap-6">
            <div class="flex items-start gap-4 min-w-0">
                <span class="metric-icon" style="background: <?= View::e($accountColor) ?>1a; color: <?= View::e($accountColor) ?>;"><?= AccountTypeIcons::svg($account['account_type']) ?></span>
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-1"><?= View::e(AccountTypeLabels::label($account['account_type'])) ?></div>
                    <div class="text-2xl font-semibold text-stone-900 dark:text-white truncate"><?= View::e($account['name']) ?></div>
                    <?php if (!empty($account['institution_name'])): ?>
                        <div class="text-sm text-stone-500 dark:text-stone-400 truncate"><?= View::e($account['institution_name']) ?></div>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 mt-3">
                        <span class="delta-chip <?= $account['include_in_net_worth'] ? 'delta-chip-ok' : 'delta-chip-neutral' ?>">
                            <?= $account['include_in_net_worth'] ? 'Net worth' : 'Not in net worth' ?>
                        </span>
                        <span class="delta-chip <?= $account['include_in_budget'] ? 'delta-chip-ok' : 'delta-chip-neutral' ?>">
                            <?= $account['include_in_budget'] ? 'Budgets' : 'Not in budgets' ?>
                        </span>
                        <?php if ($account['status'] === 'archived'): ?>
                            <span class="badge">Archived</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-1">Current balance</div>
                <div class="text-3xl font-semibold text-stone-900 dark:text-white"><?= Money::format($account['current_balance']) ?></div>
            </div>
        </div>

        <!-- Adjust balance -->
        <div class="card">
            <div class="flex items-center gap-2 mb-4">
                <span class="metric-icon" style="background: rgba(168, 162, 158, .14); color: #78716c;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                </span>
                <h2 class="font-semibold text-stone-900 dark:text-white">Adjust balance</h2>
            </div>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/balance" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div>
                    <label for="new_balance" class="field-label">New balance</label>
                    <input type="text" inputmode="decimal" id="new_balance" name="new_balance" required value="<?= View::e($account['current_balance']) ?>" class="field-input" style="max-width: 12rem;">
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label for="note" class="field-label">Note (optional)</label>
                    <input type="text" id="note" name="note" class="field-input" placeholder="e.g. Statement update">
                </div>
                <button type="submit" class="btn-primary">Update balance</button>
            </form>
        </div>

        <?php if ($history !== []): ?>
        <!-- Recent changes -->
        <div class="card">
            <div class="flex items-center gap-2 mb-2">
                <span class="metric-icon" style="background: rgba(168, 162, 158, .14); color: #78716c;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                </span>
                <h2 class="font-semibold text-stone-900 dark:text-white">Recent changes</h2>
            </div>
            <div class="divide-y divide-stone-100 dark:divide-stone-800">
                <?php foreach ($history as $entry): ?>
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <div class="text-sm text-stone-900 dark:text-white truncate"><?= View::e($entry['note'] ?? 'Balance update') ?></div>
                            <div class="text-xs text-stone-500 dark:text-stone-400 truncate"><?= View::e($entry['created_at']) ?> &middot; <?= View::e($entry['changed_by_name']) ?></div>
                        </div>
                        <div class="text-sm flex-shrink-0">
                            <span class="text-stone-500 dark:text-stone-400"><?= Money::format($entry['previous_balance']) ?></span>
                            <span class="text-stone-400 dark:text-stone-600 mx-1">&rarr;</span>
                            <span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($entry['new_balance']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Account details -->
        <form method="POST" action="/accounts/<?= (int) $account['id'] ?>" class="card">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <h2 class="font-semibold text-stone-900 dark:text-white mb-5">Account details</h2>

            <div class="space-y-4">
                <div class="account-detail-panel">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="metric-icon" style="background: rgba(168, 162, 158, .14); color: #78716c;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                        </span>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400">Basic info</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="name" class="field-label">Account name</label>
                            <input type="text" id="name" name="name" required value="<?= View::e($account['name']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="account_type" class="field-label">Type</label>
                            <select id="account_type" name="account_type" required class="field-input">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= View::e($type) ?>" <?= $account['account_type'] === $type ? 'selected' : '' ?>>
                                        <?= View::e(AccountTypeLabels::label($type)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="institution_name" class="field-label">Institution (optional)</label>
                            <input type="text" id="institution_name" name="institution_name" value="<?= View::e($account['institution_name'] ?? '') ?>" class="field-input" placeholder="Display name only">
                        </div>
                    </div>
                </div>

                <div class="account-detail-panel">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="metric-icon" style="background: rgba(168, 162, 158, .14); color: #78716c;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </span>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400">Balances &amp; limits</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="available_balance" class="field-label">Available balance (optional)</label>
                            <input type="text" inputmode="decimal" id="available_balance" name="available_balance" value="<?= View::e($account['available_balance'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="credit_limit" class="field-label">Credit limit (optional)</label>
                            <input type="text" inputmode="decimal" id="credit_limit" name="credit_limit" value="<?= View::e($account['credit_limit'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="interest_rate" class="field-label">Interest rate % (optional)</label>
                            <input type="text" inputmode="decimal" id="interest_rate" name="interest_rate" value="<?= View::e($account['interest_rate'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="minimum_payment" class="field-label">Minimum payment (optional)</label>
                            <input type="text" inputmode="decimal" id="minimum_payment" name="minimum_payment" value="<?= View::e($account['minimum_payment'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="payment_due_day" class="field-label">Payment due day (optional)</label>
                            <input type="number" min="1" max="31" id="payment_due_day" name="payment_due_day" value="<?= View::e($account['payment_due_day'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="original_balance" class="field-label">Original loan balance (optional)</label>
                            <input type="text" inputmode="decimal" id="original_balance" name="original_balance" value="<?= View::e($account['original_balance'] ?? '') ?>" class="field-input">
                        </div>
                    </div>
                </div>

                <div class="account-detail-panel">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="metric-icon" style="background: rgba(168, 162, 158, .14); color: #78716c;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
                        </span>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400">Appearance</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="color-swatch-picker">
                            <span class="color-swatch" style="background-color: <?= View::e($account['color'] ?? '#e2694b') ?>;"></span>
                            <input type="color" id="color" name="color" value="<?= View::e($account['color'] ?? '#e2694b') ?>">
                        </label>
                        <div class="text-sm text-stone-500 dark:text-stone-400">Used for this account's icon and hero accent</div>
                    </div>
                </div>

                <div class="account-detail-panel">
                    <label for="notes" class="field-label">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="3" class="field-input"><?= View::e($account['notes'] ?? '') ?></textarea>
                </div>

                <div class="account-detail-panel flex items-center gap-8">
                    <label class="flex items-center gap-3 text-sm text-stone-700 dark:text-stone-300 cursor-pointer">
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" name="include_in_net_worth" value="1" <?= $account['include_in_net_worth'] ? 'checked' : '' ?> class="sr-only peer">
                            <span class="w-11 h-6 bg-stone-300 dark:bg-stone-700 rounded-full peer-checked:bg-terracotta-600 transition-colors"></span>
                            <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                        </span>
                        Include in net worth
                    </label>
                    <label class="flex items-center gap-3 text-sm text-stone-700 dark:text-stone-300 cursor-pointer">
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" name="include_in_budget" value="1" <?= $account['include_in_budget'] ? 'checked' : '' ?> class="sr-only peer">
                            <span class="w-11 h-6 bg-stone-300 dark:bg-stone-700 rounded-full peer-checked:bg-terracotta-600 transition-colors"></span>
                            <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                        </span>
                        Include in budgets
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary mt-5">Save changes</button>
        </form>

        <div class="card">
            <h2 class="font-medium text-stone-900 dark:text-white mb-2">
                <?= $account['status'] === 'archived' ? 'Restore account' : 'Archive account' ?>
            </h2>
            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">
                <?= $account['status'] === 'archived'
                    ? 'This account is archived and hidden from your active accounts list.'
                    : 'Archiving hides this account from your active list without deleting its history.' ?>
            </p>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/<?= $account['status'] === 'archived' ? 'restore' : 'archive' ?>">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn-secondary">
                    <?= $account['status'] === 'archived' ? 'Restore' : 'Archive' ?>
                </button>
            </form>
        </div>
        </main>
        </div>
    </div>
</body>
</html>
