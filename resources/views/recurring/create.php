<?php

/** @var array $accounts */
/** @var array $categories */
/** @var list<string> $frequencies */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var array $old */

use App\Support\View;

$frequencyLabel = [
    'weekly' => 'Weekly',
    'biweekly' => 'Every 2 weeks',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'annually' => 'Annually',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add recurring item · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'recurring']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div>
                    <a href="/recurring" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Recurring</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Add recurring item</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/recurring" class="card space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-3">Basic info</h3>
                        <div>
                            <label for="name" class="field-label">Name</label>
                            <input type="text" id="name" name="name" required value="<?= View::e($old['name'] ?? '') ?>" class="field-input" placeholder="e.g. Netflix, Rent, Paycheck">
                        </div>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-3">Schedule &amp; account</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="recurring_type" class="field-label">Type</label>
                                <select id="recurring_type" name="recurring_type" required class="field-input">
                                    <option value="expense" <?= ($old['recurring_type'] ?? 'expense') === 'expense' ? 'selected' : '' ?>>Expense</option>
                                    <option value="income" <?= ($old['recurring_type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                                </select>
                            </div>
                            <div>
                                <label for="expected_amount" class="field-label">Expected amount</label>
                                <input type="text" inputmode="decimal" id="expected_amount" name="expected_amount" required value="<?= View::e($old['expected_amount'] ?? '') ?>" class="field-input" placeholder="0.00">
                            </div>
                            <div>
                                <label for="frequency" class="field-label">Frequency</label>
                                <select id="frequency" name="frequency" required class="field-input">
                                    <?php foreach ($frequencies as $frequency): ?>
                                        <option value="<?= View::e($frequency) ?>" <?= ($old['frequency'] ?? 'monthly') === $frequency ? 'selected' : '' ?>><?= View::e($frequencyLabel[$frequency] ?? $frequency) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="next_due_date" class="field-label">Next due date</label>
                                <input type="date" id="next_due_date" name="next_due_date" required value="<?= View::e($old['next_due_date'] ?? date('Y-m-d')) ?>" class="field-input">
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
                                <label for="category_id" class="field-label">Category (optional)</label>
                                <select id="category_id" name="category_id" class="field-input">
                                    <option value="">No category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === ($old['category_id'] ?? '') ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6">
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($old['notes'] ?? '') ?></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                        <input type="checkbox" name="auto_pay" value="1" <?= ($old['auto_pay'] ?? false) ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                        This is on auto-pay at the biller
                    </label>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Add recurring item</button>
                        <a href="/recurring" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
