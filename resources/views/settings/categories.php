<?php

/** @var list<array{group: array|null, categories: array}> $expenseSections */
/** @var list<array{group: array|null, categories: array}> $incomeSections */
/** @var array $expenseGroups */
/** @var array $incomeGroups */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $old */

use App\Support\SettingsIcons;
use App\Support\View;

$editingId = $old['id'] ?? null;

$renderCategoryRow = function (array $category) use ($csrfToken, $editingId, $old, $expenseGroups, $incomeGroups): void {
    $isEditing = $editingId !== null && (string) $category['id'] === (string) $editingId;
    $name = $isEditing ? ($old['name'] ?? $category['name']) : $category['name'];
    $isArchived = $category['archived_at'] !== null;
    $groups = $category['type'] === 'expense' ? $expenseGroups : $incomeGroups;
    $currentGroupId = $isEditing ? ($old['group_id'] ?? $category['group_id']) : $category['group_id'];
    ?>
    <div class="flex items-center justify-between gap-2 py-2.5 <?= $isArchived ? 'opacity-50' : '' ?>">
        <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>" class="flex items-center gap-2 flex-1 min-w-0">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="type" value="<?= View::e($category['type']) ?>">
            <input type="text" name="name" value="<?= View::e($name) ?>" class="field-input flex-1 min-w-0">
            <select name="group_id" class="field-input" style="max-width: 9.5rem;">
                <option value="">Ungrouped</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= (int) $group['id'] ?>" <?= (string) $group['id'] === (string) $currentGroupId ? 'selected' : '' ?>><?= View::e($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Save</button>
        </form>
        <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>/<?= $isArchived ? 'restore' : 'archive' ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn-secondary"><?= $isArchived ? 'Restore' : 'Archive' ?></button>
        </form>
    </div>
    <?php
};

$renderSections = function (array $sections) use ($csrfToken, $renderCategoryRow): void {
    foreach ($sections as $section) {
        $group = $section['group'];
        ?>
        <div class="card">
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-medium text-stone-900 dark:text-white"><?= View::e($group['name'] ?? 'Ungrouped') ?></h3>
                <?php if ($group !== null): ?>
                    <details class="text-sm">
                        <summary class="cursor-pointer text-terracotta-600 dark:text-terracotta-400 hover:underline list-none">Edit</summary>
                        <div class="mt-3 flex items-center gap-2">
                            <form method="POST" action="/settings/category-groups/<?= (int) $group['id'] ?>" class="flex items-center gap-2 flex-1">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <input type="text" name="name" value="<?= View::e($group['name']) ?>" class="field-input">
                                <button type="submit" class="btn-secondary">Save</button>
                            </form>
                            <form method="POST" action="/settings/category-groups/<?= (int) $group['id'] ?>/delete" onsubmit="return confirm('Delete the \'<?= View::e($group['name']) ?>\' section? Its categories become ungrouped.');">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button type="submit" class="btn-secondary">Delete</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
            <?php if ($section['categories'] === []): ?>
                <p class="text-sm text-stone-500 dark:text-stone-400 py-2">No categories yet.</p>
            <?php else: ?>
                <div class="divide-y divide-stone-100 dark:divide-stone-800">
                    <?php foreach ($section['categories'] as $category): ?>
                        <?php $renderCategoryRow($category); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'categories']); ?>

                    <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('categories') ?></span>
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Categories</h2>
                    </div>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Changes here apply everywhere categories are used — transactions, budgets, and reports.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Create category</h2>
                    <form method="POST" action="/settings/categories" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div class="flex-1 min-w-[10rem]">
                            <label for="name" class="field-label">Name</label>
                            <input type="text" id="name" name="name" required value="<?= $editingId === null ? View::e($old['name'] ?? '') : '' ?>" class="field-input">
                        </div>
                        <div>
                            <label for="type" class="field-label">Type</label>
                            <select id="type" name="type" class="field-input">
                                <option value="expense" <?= ($editingId === null ? ($old['type'] ?? 'expense') : 'expense') === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="income" <?= $editingId === null && ($old['type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary">Create category</button>
                    </form>
                </div>

                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Expenses</h2>
                    <details class="text-sm">
                        <summary class="cursor-pointer text-terracotta-600 dark:text-terracotta-400 hover:underline list-none">+ Create group</summary>
                        <form method="POST" action="/settings/category-groups" class="mt-2 flex items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <input type="hidden" name="type" value="expense">
                            <input type="text" name="name" placeholder="Section name" required class="field-input">
                            <button type="submit" class="btn-secondary">Add</button>
                        </form>
                    </details>
                </div>
                <div class="space-y-4">
                    <?php $renderSections($expenseSections); ?>
                </div>

                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Income</h2>
                    <details class="text-sm">
                        <summary class="cursor-pointer text-terracotta-600 dark:text-terracotta-400 hover:underline list-none">+ Create group</summary>
                        <form method="POST" action="/settings/category-groups" class="mt-2 flex items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <input type="hidden" name="type" value="income">
                            <input type="text" name="name" placeholder="Section name" required class="field-input">
                            <button type="submit" class="btn-secondary">Add</button>
                        </form>
                    </details>
                </div>
                <div class="space-y-4">
                    <?php $renderSections($incomeSections); ?>
                </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
