<?php

/** @var array $accounts */
/** @var array $members */
/** @var list<string> $goalTypes */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var array $old */

use App\Support\View;

$goalTypeLabel = [
    'emergency_fund' => 'Emergency Fund',
    'vacation' => 'Vacation',
    'home_project' => 'Home Project',
    'vehicle' => 'Vehicle',
    'baby_expenses' => 'Baby Expenses',
    'debt_payoff' => 'Debt Payoff',
    'investment_target' => 'Investment Target',
    'mortgage_payoff' => 'Mortgage Payoff',
    'general_savings' => 'General Savings',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add goal · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'goals']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div>
                    <a href="/goals" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Goals</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Add goal</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/goals" class="card space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-3">Basic info</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="field-label">Name</label>
                                <input type="text" id="name" name="name" required value="<?= View::e($old['name'] ?? '') ?>" class="field-input" placeholder="e.g. Emergency Fund">
                            </div>
                            <div>
                                <label for="description" class="field-label">Description (optional)</label>
                                <textarea id="description" name="description" rows="2" class="field-input"><?= View::e($old['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-800 pt-6">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-3">Goal details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="goal_type" class="field-label">Goal type</label>
                                <select id="goal_type" name="goal_type" required class="field-input">
                                    <?php foreach ($goalTypes as $type): ?>
                                        <option value="<?= View::e($type) ?>" <?= ($old['goal_type'] ?? 'general_savings') === $type ? 'selected' : '' ?>><?= View::e($goalTypeLabel[$type] ?? $type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="target_amount" class="field-label">Target amount</label>
                                <input type="text" inputmode="decimal" id="target_amount" name="target_amount" required value="<?= View::e($old['target_amount'] ?? '') ?>" class="field-input" placeholder="0.00">
                            </div>
                            <div>
                                <label for="target_date" class="field-label">Target date (optional)</label>
                                <input type="date" id="target_date" name="target_date" value="<?= View::e($old['target_date'] ?? '') ?>" class="field-input">
                            </div>
                            <div>
                                <label for="planned_monthly_contribution" class="field-label">Planned monthly contribution (optional)</label>
                                <input type="text" inputmode="decimal" id="planned_monthly_contribution" name="planned_monthly_contribution" value="<?= View::e($old['planned_monthly_contribution'] ?? '') ?>" class="field-input" placeholder="0.00">
                            </div>
                            <div>
                                <label for="responsible_user_id" class="field-label">Responsible member (optional)</label>
                                <select id="responsible_user_id" name="responsible_user_id" class="field-input">
                                    <option value="">Whole household</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?= (int) $member['id'] ?>" <?= (string) $member['id'] === ($old['responsible_user_id'] ?? '') ? 'selected' : '' ?>><?= View::e($member['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="linked_account_id" class="field-label">Linked account (optional)</label>
                                <select id="linked_account_id" name="linked_account_id" class="field-input">
                                    <option value="">None</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === ($old['linked_account_id'] ?? '') ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field-help">Just a reference — contributions are still logged manually.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Add goal</button>
                        <a href="/goals" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
