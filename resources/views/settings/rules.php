<?php

/** @var list<array{rule: array, conditions: array, actions: array}> $rules */
/** @var array<int, string> $accountNames */
/** @var array<int, string> $categoryNames */
/** @var array<int, string> $tagNames */
/** @var string $csrfToken */
/** @var string|null $notice */
/** @var string|null $error */

use App\Support\SettingsIcons;
use App\Support\View;

$conditionLabel = function (array $condition) use ($accountNames): string {
    $value = $condition['value'];
    return match ($condition['field']) {
        'payee' => 'Payee contains "' . $value . '"',
        'notes' => 'Notes contains "' . $value . '"',
        'amount' => 'Amount ' . match ($condition['operator']) {
            'greater_than' => 'is greater than',
            'less_than' => 'is less than',
            default => 'equals',
        } . ' $' . $value,
        'account_id' => 'Account is ' . ($accountNames[(int) $value] ?? 'an unknown account'),
        'transaction_type' => 'Type is ' . ucfirst($value),
        default => 'Unknown condition',
    };
};

$actionLabels = function (array $actions) use ($categoryNames, $tagNames): array {
    $labels = [];
    foreach ($actions as $action) {
        $labels[] = match ($action['action_type']) {
            'set_category' => 'Set category to ' . ($categoryNames[(int) $action['value']] ?? 'an unknown category'),
            'set_payee' => 'Rename payee to "' . $action['value'] . '"',
            'add_tag' => 'Add tag ' . ($tagNames[(int) $action['value']] ?? 'an unknown tag'),
            'exclude_from_reports' => 'Hide from reports',
            default => null,
        };
    }
    return array_values(array_filter($labels));
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rules · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'rules']); ?>

                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('rules') ?></span>
                                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Rules</h2>
                                </div>
                                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Automatically categorize, rename, tag, or mark transactions as they come in — from manual entry, CSV import, or recurring bills.</p>
                            </div>
                            <a href="/settings/rules/create" class="btn-primary">New rule</a>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <?php if ($rules === []): ?>
                            <div class="card text-center py-12">
                                <p class="text-stone-500 dark:text-stone-400">No rules yet. Create one to automate categorization.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($rules as $entry): ?>
                                    <?php $rule = $entry['rule']; $isActive = (int) $rule['is_active'] === 1; ?>
                                    <div class="card <?= $isActive ? '' : 'opacity-60' ?>">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="font-medium text-stone-900 dark:text-white"><?= View::e($rule['name']) ?><?= $isActive ? '' : ' (paused)' ?></h3>
                                                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                                                    When <?= implode(' and ', array_map(fn (array $c): string => View::e($conditionLabel($c)), $entry['conditions'])) ?>
                                                </p>
                                                <p class="text-sm text-stone-500 dark:text-stone-400">
                                                    Then <?= implode(', ', array_map(fn (string $label): string => View::e($label), $actionLabels($entry['actions']))) ?>
                                                </p>
                                            </div>
                                            <div class="flex flex-col items-end gap-2 shrink-0">
                                                <div class="flex gap-2">
                                                    <a href="/settings/rules/<?= (int) $rule['id'] ?>/edit" class="btn-secondary">Edit</a>
                                                    <a href="/settings/rules/<?= (int) $rule['id'] ?>/apply" class="btn-secondary">Apply to existing</a>
                                                </div>
                                                <div class="flex gap-2">
                                                    <form method="POST" action="/settings/rules/<?= (int) $rule['id'] ?>/toggle">
                                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                        <button type="submit" class="btn-secondary"><?= $isActive ? 'Pause' : 'Activate' ?></button>
                                                    </form>
                                                    <form method="POST" action="/settings/rules/<?= (int) $rule['id'] ?>/delete" onsubmit="return confirm('Delete the rule \'<?= View::e($rule['name']) ?>\'? This does not undo anything it already applied.');">
                                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                        <button type="submit" class="btn-secondary">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
