<?php

/** @var list<array{group: array|null, active: list<array{category: array, children: array}>, archived: list<array{category: array, children: array}>}> $expenseSections */
/** @var list<array{group: array|null, active: list<array{category: array, children: array}>, archived: list<array{category: array, children: array}>}> $incomeSections */
/** @var array $expenseGroups */
/** @var array $incomeGroups */
/** @var array $expenseParentOptions */
/** @var array $incomeParentOptions */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $old */

use App\Support\CategoryGroupIcons;
use App\Support\SettingsIcons;
use App\Support\View;

$editingId = $old['id'] ?? null;

$arrowUpIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
$dragHandleIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/></svg>';

/**
 * One category row — the primary control just renames it (group/parent
 * hidden fields preserve whatever they already are), everything else
 * (which section it's filed under, which category it nests under, merge,
 * archive) lives behind "More" so the default view isn't five controls
 * wide per row.
 */
$renderCategoryRow = function (array $category, array $groups, array $parentOptions, bool $isChild = false) use ($csrfToken, $dragHandleIcon): void {
    $isArchived = $category['archived_at'] !== null;
    ?>
    <div class="<?= $isChild ? 'ml-7' : '' ?> <?= $isArchived ? 'opacity-50' : '' ?>">
        <div class="flex items-center justify-between gap-2 py-2">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <?php if (!$isChild && !$isArchived): ?>
                    <span class="text-stone-300 dark:text-stone-700 hover:text-stone-500 dark:hover:text-stone-500 flex-shrink-0 cursor-grab" style="width:1rem;height:1rem;" title="Drag to reorder"><?= $dragHandleIcon ?></span>
                <?php endif; ?>
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($category['color'] ?: '#a8a29e') ?>" title="Category color"></span>
                <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>" class="flex items-center gap-2 flex-1 min-w-0">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <input type="hidden" name="type" value="<?= View::e($category['type']) ?>">
                    <input type="hidden" name="group_id" value="<?= $category['group_id'] !== null ? (int) $category['group_id'] : '' ?>">
                    <input type="hidden" name="parent_category_id" value="<?= $category['parent_category_id'] !== null ? (int) $category['parent_category_id'] : '' ?>">
                    <input type="text" name="name" value="<?= View::e($category['name']) ?>" class="field-input flex-1 min-w-0">
                    <button type="submit" class="btn-secondary">Save</button>
                </form>
            </div>
            <details class="text-sm flex-shrink-0">
                <summary class="cursor-pointer text-stone-500 dark:text-stone-400 hover:underline list-none">More</summary>
                <div class="mt-3 pb-1">
                    <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>" class="flex flex-wrap items-end gap-3 mb-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input type="hidden" name="name" value="<?= View::e($category['name']) ?>">
                        <input type="hidden" name="type" value="<?= View::e($category['type']) ?>">
                        <div>
                            <label class="field-label">Section</label>
                            <select name="group_id" class="field-input" style="max-width: 11rem;">
                                <option value="">Ungrouped</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= (int) $group['id'] ?>" <?= (string) $group['id'] === (string) $category['group_id'] ? 'selected' : '' ?>><?= View::e($group['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Parent category</label>
                            <select name="parent_category_id" class="field-input" style="max-width: 11rem;">
                                <option value="">No parent — top-level</option>
                                <?php foreach ($parentOptions as $parent): ?>
                                    <?php if ((int) $parent['id'] === (int) $category['id']) continue; ?>
                                    <option value="<?= (int) $parent['id'] ?>" <?= (string) $parent['id'] === (string) $category['parent_category_id'] ? 'selected' : '' ?>><?= View::e($parent['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-help">Filed under the parent's own section when set — the section above is ignored. A category with its own subcategories can't become one itself.</p>
                        </div>
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>
                    <div class="flex items-center gap-2">
                        <a href="/settings/categories/<?= (int) $category['id'] ?>/merge" class="btn-secondary">Merge into&hellip;</a>
                        <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>/<?= $isArchived ? 'restore' : 'archive' ?>">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button type="submit" class="btn-secondary"><?= $isArchived ? 'Restore' : 'Archive' ?></button>
                        </form>
                    </div>
                </div>
            </details>
        </div>
    </div>
    <?php
};

$renderSections = function (array $sections, array $groups, array $parentOptions) use ($csrfToken, $renderCategoryRow): void {
    foreach ($sections as $section) {
        $group = $section['group'];
        ?>
        <div class="card">
            <div class="flex items-center justify-between mb-1">
                <span class="flex items-center gap-2.5 min-w-0">
                    <span class="budget-group-icon"><?= CategoryGroupIcons::forName($group['name'] ?? null) ?></span>
                    <h3 class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($group['name'] ?? 'Ungrouped') ?></h3>
                </span>
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

            <?php if ($section['active'] === [] && $section['archived'] === []): ?>
                <p class="text-sm text-stone-500 dark:text-stone-400 py-2">No categories yet.</p>
            <?php else: ?>
                <?php if ($section['active'] === []): ?>
                    <p class="text-sm text-stone-500 dark:text-stone-400 py-2">No active categories in this section.</p>
                <?php else: ?>
                    <div class="divide-y divide-stone-100 dark:divide-stone-800 category-group-list" data-group-id="<?= $group !== null ? (int) $group['id'] : 0 ?>">
                        <?php foreach ($section['active'] as $row): ?>
                            <div draggable="true" data-category-id="<?= (int) $row['category']['id'] ?>">
                                <?php $renderCategoryRow($row['category'], $groups, $parentOptions); ?>
                                <?php foreach ($row['children'] as $child): ?>
                                    <?php $renderCategoryRow($child, $groups, $parentOptions, true); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($section['archived'] !== []): ?>
                    <details class="mt-3 border-t border-stone-100 dark:border-stone-800 pt-3">
                        <summary class="cursor-pointer text-sm text-stone-500 dark:text-stone-400 hover:underline list-none"><?= count($section['archived']) ?> archived</summary>
                        <div class="divide-y divide-stone-100 dark:divide-stone-800 mt-2">
                            <?php foreach ($section['archived'] as $row): ?>
                                <div>
                                    <?php $renderCategoryRow($row['category'], $groups, $parentOptions); ?>
                                    <?php foreach ($row['children'] as $child): ?>
                                        <?php $renderCategoryRow($child, $groups, $parentOptions, true); ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
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
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Changes here apply everywhere categories are used — transactions, budgets, and reports. Drag a category to reorder it within its section, or open "More" on any row to move it, nest it under another category, merge it into another, or archive it.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Create category</h2>
                    <form method="POST" action="/settings/categories" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div class="flex flex-wrap items-end gap-3">
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
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="group_id" class="field-label">Section (optional)</label>
                                <select id="group_id" name="group_id" class="field-input" style="max-width: 12rem;">
                                    <option value="">Ungrouped</option>
                                    <optgroup label="Expense">
                                        <?php foreach ($expenseGroups as $group): ?>
                                            <option value="<?= (int) $group['id'] ?>"><?= View::e($group['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Income">
                                        <?php foreach ($incomeGroups as $group): ?>
                                            <option value="<?= (int) $group['id'] ?>"><?= View::e($group['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            <div>
                                <label for="parent_category_id" class="field-label">Parent category (optional)</label>
                                <select id="parent_category_id" name="parent_category_id" class="field-input" style="max-width: 12rem;">
                                    <option value="">No parent — top-level</option>
                                    <optgroup label="Expense">
                                        <?php foreach ($expenseParentOptions as $parent): ?>
                                            <option value="<?= (int) $parent['id'] ?>"><?= View::e($parent['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Income">
                                        <?php foreach ($incomeParentOptions as $parent): ?>
                                            <option value="<?= (int) $parent['id'] ?>"><?= View::e($parent['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="field-help">Must match the Type above, and files this into the parent's own section.</p>
                            </div>
                            <button type="submit" class="btn-primary">Create category</button>
                        </div>
                    </form>
                </div>

                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2.5">
                        <span class="metric-icon" style="background: rgba(226, 105, 75, .14); color: #c94f32;"><?= $arrowDownIcon ?></span>
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Expenses</h2>
                    </span>
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
                    <?php $renderSections($expenseSections, $expenseGroups, $expenseParentOptions); ?>
                </div>

                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2.5">
                        <span class="metric-icon" style="background: rgba(52, 211, 153, .14); color: #059669;"><?= $arrowUpIcon ?></span>
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Income</h2>
                    </span>
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
                    <?php $renderSections($incomeSections, $incomeGroups, $incomeParentOptions); ?>
                </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('/assets/js/settings-categories.js') ?>" defer></script>
</body>
</html>
