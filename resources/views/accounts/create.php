<?php

/** @var string $csrfToken */
/** @var string|null $error */
/** @var array $old */
/** @var array $types */

use App\Support\AccountTypeLabels;
use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add account · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

        <div class="app-content">
        <main class="page-main">
        <div>
            <a href="/accounts" class="text-sm text-slate-500 dark:text-slate-400 hover:underline">&larr; Accounts</a>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white mt-1">Add account</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/accounts" class="card space-y-5">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label for="name" class="field-label">Account name</label>
                    <input type="text" id="name" name="name" required value="<?= View::e($old['name'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="account_type" class="field-label">Type</label>
                    <select id="account_type" name="account_type" required class="field-input">
                        <option value="">Select a type&hellip;</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= View::e($type) ?>" <?= ($old['account_type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= View::e(AccountTypeLabels::label($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="institution_name" class="field-label">Institution (optional)</label>
                    <input type="text" id="institution_name" name="institution_name" value="<?= View::e($old['institution_name'] ?? '') ?>" class="field-input" placeholder="Display name only">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="current_balance" class="field-label">Current balance</label>
                    <input type="text" inputmode="decimal" id="current_balance" name="current_balance" required value="<?= View::e($old['current_balance'] ?? '0.00') ?>" class="field-input">
                </div>
                <div>
                    <label for="available_balance" class="field-label">Available balance (optional)</label>
                    <input type="text" inputmode="decimal" id="available_balance" name="available_balance" value="<?= View::e($old['available_balance'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="credit_limit" class="field-label">Credit limit (optional)</label>
                    <input type="text" inputmode="decimal" id="credit_limit" name="credit_limit" value="<?= View::e($old['credit_limit'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="interest_rate" class="field-label">Interest rate % (optional)</label>
                    <input type="text" inputmode="decimal" id="interest_rate" name="interest_rate" value="<?= View::e($old['interest_rate'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="minimum_payment" class="field-label">Minimum payment (optional)</label>
                    <input type="text" inputmode="decimal" id="minimum_payment" name="minimum_payment" value="<?= View::e($old['minimum_payment'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="payment_due_day" class="field-label">Payment due day (optional)</label>
                    <input type="number" min="1" max="31" id="payment_due_day" name="payment_due_day" value="<?= View::e($old['payment_due_day'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="original_balance" class="field-label">Original loan balance (optional)</label>
                    <input type="text" inputmode="decimal" id="original_balance" name="original_balance" value="<?= View::e($old['original_balance'] ?? '') ?>" class="field-input">
                </div>
                <div>
                    <label for="color" class="field-label">Color (optional)</label>
                    <input type="color" id="color" name="color" value="<?= View::e($old['color'] ?? '#3b82f6') ?>" class="field-input h-10">
                </div>
            </div>

            <div>
                <label for="notes" class="field-label">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="3" class="field-input"><?= View::e($old['notes'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="include_in_net_worth" value="1" checked class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
                    Include in net worth
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="include_in_budget" value="1" checked class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
                    Include in budgets
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Create account</button>
                <a href="/accounts" class="btn-secondary">Cancel</a>
            </div>
        </form>
        </main>
        </div>
    </div>
</body>
</html>
