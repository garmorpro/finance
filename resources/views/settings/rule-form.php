<?php

/** @var array|null $rule */
/** @var array $conditions */
/** @var array $actions */
/** @var array $accounts */
/** @var array $categories */
/** @var array $tags */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

$isEdit = $rule !== null;
$formAction = $isEdit ? '/settings/rules/' . (int) $rule['id'] : '/settings/rules';

$conditionByField = [];
foreach ($conditions as $condition) {
    $conditionByField[$condition['field']] = $condition;
}

$actionByType = [];
$selectedTagIds = [];
foreach ($actions as $action) {
    if ($action['action_type'] === 'add_tag') {
        $selectedTagIds[] = (int) $action['value'];
    } else {
        $actionByType[$action['action_type']] = $action;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit rule' : 'New rule' ?> · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'rules']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/settings/rules" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Rules</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1"><?= $isEdit ? 'Edit rule' : 'New rule' ?></h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Applies automatically to new transactions from manual entry, CSV import, and recurring bills — not to transfers.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= View::e($formAction) ?>" class="card space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <label for="name" class="field-label">Rule name</label>
                        <input type="text" id="name" name="name" required value="<?= View::e($rule['name'] ?? '') ?>" class="field-input" placeholder="e.g. Amazon &rarr; Shopping">
                    </div>

                    <div>
                        <h2 class="font-medium text-stone-900 dark:text-white mb-1">Conditions</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-3">Fill in as many as you need — every one you set must match (all are ANDed together).</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="payee_contains" class="field-label">Payee contains</label>
                                <input type="text" id="payee_contains" name="payee_contains" value="<?= View::e($conditionByField['payee']['value'] ?? '') ?>" class="field-input" placeholder="e.g. Amazon">
                            </div>
                            <div>
                                <label for="notes_contains" class="field-label">Notes contains</label>
                                <input type="text" id="notes_contains" name="notes_contains" value="<?= View::e($conditionByField['notes']['value'] ?? '') ?>" class="field-input">
                            </div>
                            <div>
                                <label for="amount_operator" class="field-label">Amount</label>
                                <div class="flex gap-2">
                                    <select id="amount_operator" name="amount_operator" class="field-input">
                                        <option value="">Any</option>
                                        <option value="equals" <?= ($conditionByField['amount']['operator'] ?? '') === 'equals' ? 'selected' : '' ?>>Equals</option>
                                        <option value="greater_than" <?= ($conditionByField['amount']['operator'] ?? '') === 'greater_than' ? 'selected' : '' ?>>Greater than</option>
                                        <option value="less_than" <?= ($conditionByField['amount']['operator'] ?? '') === 'less_than' ? 'selected' : '' ?>>Less than</option>
                                    </select>
                                    <input type="text" inputmode="decimal" name="amount_value" value="<?= View::e($conditionByField['amount']['value'] ?? '') ?>" class="field-input" placeholder="0.00">
                                </div>
                            </div>
                            <div>
                                <label for="account_id" class="field-label">Account</label>
                                <select id="account_id" name="account_id" class="field-input">
                                    <option value="">Any</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === ($conditionByField['account_id']['value'] ?? '') ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="transaction_type" class="field-label">Type</label>
                                <select id="transaction_type" name="transaction_type" class="field-input">
                                    <option value="">Any</option>
                                    <option value="expense" <?= ($conditionByField['transaction_type']['value'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense</option>
                                    <option value="income" <?= ($conditionByField['transaction_type']['value'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="font-medium text-stone-900 dark:text-white mb-1">Actions</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-3">What to do when every condition above matches.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="category_id" class="field-label">Set category to</label>
                                <select id="category_id" name="category_id" class="field-input">
                                    <option value="">Don't change</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === ($actionByType['set_category']['value'] ?? '') ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="payee_rename" class="field-label">Rename payee to</label>
                                <input type="text" id="payee_rename" name="payee_rename" value="<?= View::e($actionByType['set_payee']['value'] ?? '') ?>" class="field-input">
                            </div>
                            <?php if ($tags !== []): ?>
                                <div class="sm:col-span-2">
                                    <label for="tag_ids" class="field-label">Add tags</label>
                                    <select id="tag_ids" name="tag_ids[]" multiple class="field-input" size="4">
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?= (int) $tag['id'] ?>" <?= in_array((int) $tag['id'], $selectedTagIds, true) ? 'selected' : '' ?>><?= View::e($tag['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-6 mt-4">
                            <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                                <input type="checkbox" name="exclude_from_reports" value="1" <?= isset($actionByType['exclude_from_reports']) ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                                Hide from reports
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary"><?= $isEdit ? 'Save changes' : 'Create rule' ?></button>
                        <a href="/settings/rules" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
