<?php

/** @var array $account */
/** @var array $history */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $types */

use App\Support\AccountTypeLabels;
use App\Support\Money;
use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($account['name']) ?> · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

        <div class="app-content">
        <main class="page-main">
        <div class="flex items-center justify-between">
            <div>
                <a href="/accounts" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Accounts</a>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1"><?= View::e($account['name']) ?></h1>
            </div>
            <div class="text-right">
                <div class="label-text">Current balance</div>
                <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($account['current_balance']) ?></div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="font-medium text-stone-900 dark:text-white mb-4">Adjust balance</h2>
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

            <?php if ($history !== []): ?>
            <div class="mt-6">
                <h3 class="label-text mb-2">Recent changes</h3>
                <table class="table-base">
                    <thead>
                        <tr><th>When</th><th>By</th><th class="text-right">From</th><th class="text-right">To</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <tr>
                                <td class="text-stone-500 dark:text-stone-400"><?= View::e($entry['created_at']) ?></td>
                                <td><?= View::e($entry['changed_by_name']) ?></td>
                                <td class="text-right"><?= Money::format($entry['previous_balance']) ?></td>
                                <td class="text-right font-medium text-stone-900 dark:text-white"><?= Money::format($entry['new_balance']) ?></td>
                                <td class="text-stone-500 dark:text-stone-400"><?= View::e($entry['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="/accounts/<?= (int) $account['id'] ?>" class="card space-y-5">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <h2 class="font-medium text-stone-900 dark:text-white">Account details</h2>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
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

            <div class="grid grid-cols-2 gap-4">
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
                <div>
                    <label for="color" class="field-label">Color (optional)</label>
                    <input type="color" id="color" name="color" value="<?= View::e($account['color'] ?? '#e2694b') ?>" class="field-input h-10">
                </div>
            </div>

            <div>
                <label for="notes" class="field-label">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="3" class="field-input"><?= View::e($account['notes'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                    <input type="checkbox" name="include_in_net_worth" value="1" <?= $account['include_in_net_worth'] ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                    Include in net worth
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                    <input type="checkbox" name="include_in_budget" value="1" <?= $account['include_in_budget'] ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                    Include in budgets
                </label>
            </div>

            <button type="submit" class="btn-primary">Save changes</button>
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
