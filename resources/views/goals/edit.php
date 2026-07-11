<?php

/** @var array $goal */
/** @var array $contributions */
/** @var float $progressPercent */
/** @var array{months: int, date: string}|null $estimatedCompletion */
/** @var string|null $suggestedMonthlyContribution */
/** @var array $accounts */
/** @var array $members */
/** @var list<string> $goalTypes */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\Money;
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

$barWidth = max(0, min(100, $progressPercent));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit goal · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'goals']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/goals" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Goals</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1"><?= View::e($goal['name']) ?></h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="flex items-baseline justify-between mb-2">
                        <span class="text-2xl font-semibold text-stone-900 dark:text-white"><?= Money::format($goal['current_amount']) ?></span>
                        <span class="text-sm text-stone-500 dark:text-stone-400">of <?= Money::format($goal['target_amount']) ?> &middot; <?= $progressPercent ?>%</span>
                    </div>
                    <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-2 mb-4">
                        <div class="h-2 rounded-full bg-terracotta-600" style="width: <?= $barWidth ?>%"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="label-text">Amount remaining</div>
                            <div class="text-stone-900 dark:text-white"><?= Money::format(bcsub($goal['target_amount'], $goal['current_amount'], 2)) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Estimated completion</div>
                            <div class="text-stone-900 dark:text-white">
                                <?= $estimatedCompletion !== null ? View::e($estimatedCompletion['date']) . ' (estimate)' : '—' ?>
                            </div>
                        </div>
                        <?php if ($suggestedMonthlyContribution !== null): ?>
                            <div class="col-span-2">
                                <div class="label-text">Suggested monthly contribution to hit your target date</div>
                                <div class="text-stone-900 dark:text-white"><?= Money::format($suggestedMonthlyContribution) ?> (estimate)</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Add contribution</h2>
                    <form method="POST" action="/goals/<?= (int) $goal['id'] ?>/contributions" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div>
                            <label for="amount" class="field-label">Amount</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" required class="field-input" placeholder="0.00">
                            <p class="field-help">Negative to record a withdrawal.</p>
                        </div>
                        <div>
                            <label for="contribution_date" class="field-label">Date</label>
                            <input type="date" id="contribution_date" name="contribution_date" required value="<?= date('Y-m-d') ?>" class="field-input">
                        </div>
                        <div class="flex-1 min-w-[10rem]">
                            <label for="notes" class="field-label">Notes (optional)</label>
                            <input type="text" id="notes" name="notes" class="field-input">
                        </div>
                        <button type="submit" class="btn-primary">Add</button>
                    </form>
                </div>

                <?php if ($contributions !== []): ?>
                    <div class="card">
                        <h2 class="font-medium text-stone-900 dark:text-white mb-4">Contribution history</h2>
                        <table class="table-base">
                            <thead>
                                <tr><th>Date</th><th>By</th><th>Notes</th><th class="text-right">Amount</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contributions as $contribution): ?>
                                    <tr>
                                        <td><?= View::e($contribution['contribution_date']) ?></td>
                                        <td><?= View::e($contribution['user_name']) ?></td>
                                        <td class="text-stone-500 dark:text-stone-400"><?= View::e($contribution['notes'] ?? '') ?></td>
                                        <td class="text-right"><?= Money::format($contribution['amount']) ?></td>
                                        <td class="text-right">
                                            <form method="POST" action="/goals/<?= (int) $goal['id'] ?>/contributions/<?= (int) $contribution['id'] ?>/delete" onsubmit="return confirm('Remove this contribution?');">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <button type="submit" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/goals/<?= (int) $goal['id'] ?>" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <h2 class="font-medium text-stone-900 dark:text-white">Details</h2>

                    <div>
                        <label for="name" class="field-label">Name</label>
                        <input type="text" id="name" name="name" required value="<?= View::e($goal['name']) ?>" class="field-input">
                    </div>

                    <div>
                        <label for="description" class="field-label">Description (optional)</label>
                        <textarea id="description" name="description" rows="2" class="field-input"><?= View::e($goal['description'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="goal_type" class="field-label">Goal type</label>
                            <select id="goal_type" name="goal_type" required class="field-input">
                                <?php foreach ($goalTypes as $type): ?>
                                    <option value="<?= View::e($type) ?>" <?= $goal['goal_type'] === $type ? 'selected' : '' ?>><?= View::e($goalTypeLabel[$type] ?? $type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="target_amount" class="field-label">Target amount</label>
                            <input type="text" inputmode="decimal" id="target_amount" name="target_amount" required value="<?= View::e($goal['target_amount']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="target_date" class="field-label">Target date (optional)</label>
                            <input type="date" id="target_date" name="target_date" value="<?= View::e($goal['target_date'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="planned_monthly_contribution" class="field-label">Planned monthly contribution (optional)</label>
                            <input type="text" inputmode="decimal" id="planned_monthly_contribution" name="planned_monthly_contribution" value="<?= View::e($goal['planned_monthly_contribution'] ?? '') ?>" class="field-input">
                        </div>
                        <div>
                            <label for="responsible_user_id" class="field-label">Responsible member (optional)</label>
                            <select id="responsible_user_id" name="responsible_user_id" class="field-input">
                                <option value="">Whole household</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= (int) $member['id'] ?>" <?= (string) $member['id'] === (string) $goal['responsible_user_id'] ? 'selected' : '' ?>><?= View::e($member['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="linked_account_id" class="field-label">Linked account (optional)</label>
                            <select id="linked_account_id" name="linked_account_id" class="field-input">
                                <option value="">None</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === (string) $goal['linked_account_id'] ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Save changes</button>
                </form>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-2">
                        <?= $goal['status'] === 'active' ? 'Wrap up this goal' : 'Reactivate this goal' ?>
                    </h2>
                    <div class="flex gap-3">
                        <?php if ($goal['status'] === 'active'): ?>
                            <form method="POST" action="/goals/<?= (int) $goal['id'] ?>/complete">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button type="submit" class="btn-primary">Mark complete</button>
                            </form>
                            <form method="POST" action="/goals/<?= (int) $goal['id'] ?>/archive" onsubmit="return confirm('Archive this goal?');">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button type="submit" class="btn-secondary">Archive</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/goals/<?= (int) $goal['id'] ?>/reactivate">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button type="submit" class="btn-secondary">Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
