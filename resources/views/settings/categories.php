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
$chevronIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';

/**
 * Shared popup shell — every "Manage" action and "New category" open the
 * same kind of centered, backdropped dialog (mirroring the dashboard's
 * existing "Customize dashboard" modal: hidden/flex toggling via JS in
 * settings-categories.js, not the native <dialog> element) instead of
 * expanding inline, since a page with this many categories made an
 * inline form per row/card feel cramped and a permanently-open create
 * form at the top felt out of proportion with everything below it.
 */
$modalStart = function (string $id, string $title) use ($csrfToken): void {
    ?>
    <div id="<?= View::e($id) ?>" class="modal-overlay hidden fixed inset-0 z-30 items-center justify-center bg-stone-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="<?= View::e($id) ?>-title">
        <div class="card w-full max-w-sm max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h2 id="<?= View::e($id) ?>-title" class="text-lg font-semibold text-stone-900 dark:text-white"><?= View::e($title) ?></h2>
                <button type="button" data-modal-close="<?= View::e($id) ?>" class="text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200 flex-shrink-0" aria-label="Close">&times;</button>
            </div>
    <?php
};
$modalEnd = function (): void {
    ?>
        </div>
    </div>
    <?php
};

/**
 * The combined rename + organize form used inside every "Manage" panel —
 * one form, one endpoint (CategoryController::update() already accepts
 * name/group_id/parent_category_id together), instead of the two
 * separate forms the row-based layout used.
 */
$renderManageForm = function (array $category, array $groups, array $parentOptions) use ($csrfToken): void {
    ?>
    <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>" class="space-y-2.5">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="type" value="<?= View::e($category['type']) ?>">
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="field-label">Name</label>
                <input type="text" name="name" value="<?= View::e($category['name']) ?>" class="field-input">
            </div>
            <div>
                <label class="field-label">Color</label>
                <input type="color" name="color" value="<?= View::e($category['color'] ?: '#a8a29e') ?>" class="field-color">
            </div>
        </div>
        <div>
            <label class="field-label">Section</label>
            <select name="group_id" class="field-input">
                <option value="">Ungrouped</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= (int) $group['id'] ?>" <?= (string) $group['id'] === (string) $category['group_id'] ? 'selected' : '' ?>><?= View::e($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="field-label">Parent category</label>
            <select name="parent_category_id" class="field-input">
                <option value="">No parent — top-level</option>
                <?php foreach ($parentOptions as $parent): ?>
                    <?php if ((int) $parent['id'] === (int) $category['id']) continue; ?>
                    <option value="<?= (int) $parent['id'] ?>" <?= (string) $parent['id'] === (string) $category['parent_category_id'] ? 'selected' : '' ?>><?= View::e($parent['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-help">Filed under the parent's own section when set — the section above is ignored.</p>
        </div>
        <button type="submit" class="btn-secondary btn-sm">Save</button>
    </form>
    <?php
};

$renderDangerZone = function (array $category, bool $isArchived) use ($csrfToken): void {
    ?>
    <div class="flex items-center justify-between gap-2 mt-3 pt-3 border-t border-stone-100 dark:border-stone-800">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-600">Danger zone</span>
        <div class="flex items-center gap-2">
            <?php if (!$isArchived): ?>
                <a href="/settings/categories/<?= (int) $category['id'] ?>/merge" class="btn-secondary btn-sm">Merge into&hellip;</a>
            <?php endif; ?>
            <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>/<?= $isArchived ? 'restore' : 'archive' ?>">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn-secondary btn-sm <?= $isArchived ? '' : 'text-red-600 dark:text-red-400' ?>"><?= $isArchived ? 'Restore' : 'Archive' ?></button>
            </form>
        </div>
    </div>
    <?php
};

/**
 * One subcategory, nested inside its parent's card. The row itself is
 * the trigger for a popup carrying the same Manage/Danger-zone controls
 * a top-level card gets, just addressed at this specific child.
 */
$renderChildRow = function (array $child, array $groups, array $parentOptions) use ($csrfToken, $chevronIcon, $renderManageForm, $renderDangerZone, $modalStart, $modalEnd): void {
    $isArchived = $child['archived_at'] !== null;
    $modalId = 'manage-modal-' . (int) $child['id'];
    ?>
    <button type="button" data-modal-open="<?= View::e($modalId) ?>" class="flex items-center gap-2 w-full py-1.5 px-1.5 -mx-1.5 rounded-lg text-xs text-stone-600 dark:text-stone-400 hover:bg-stone-50 dark:hover:bg-stone-800 <?= $isArchived ? 'opacity-50' : '' ?>">
        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: <?= View::e($child['color'] ?: '#a8a29e') ?>"></span>
        <span class="flex-1 min-w-0 truncate text-left"><?= View::e($child['name']) ?></span>
        <span class="w-3 h-3 text-stone-400 dark:text-stone-600 flex-shrink-0"><?= $chevronIcon ?></span>
    </button>
    <?php $modalStart($modalId, ($isArchived ? 'Archived subcategory: ' : 'Manage "') . $child['name'] . ($isArchived ? '' : '"')); ?>
        <?php $renderManageForm($child, $groups, $parentOptions); ?>
        <?php $renderDangerZone($child, $isArchived); ?>
    <?php $modalEnd(); ?>
    <?php
};

$renderCatCard = function (array $category, array $children, array $groups, array $parentOptions, bool $isArchived) use ($csrfToken, $dragHandleIcon, $renderChildRow, $renderManageForm, $renderDangerZone, $modalStart, $modalEnd): void {
    $count = count($children);
    $modalId = 'manage-modal-' . (int) $category['id'];
    // No line at all for a plain top-level category — "No subcategories"
    // read as if something were missing on every card in a section that
    // just doesn't nest anything, which is the common case, not an
    // exception worth calling out.
    $meta = $isArchived ? 'Archived' : ($count > 0 ? $count . ' subcategor' . ($count === 1 ? 'y' : 'ies') : null);
    ?>
    <div class="cat-card <?= $isArchived ? 'opacity-60' : '' ?>" <?= $isArchived ? '' : 'draggable="true" data-category-id="' . (int) $category['id'] . '"' ?>>
        <div class="cat-card-bar" style="background-color: <?= View::e($category['color'] ?: '#a8a29e') ?>"></div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2 <?= $meta !== null ? 'mb-0.5' : 'mb-3' ?>">
                <h4 class="font-semibold text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></h4>
                <?php if (!$isArchived): ?>
                    <span class="text-stone-300 dark:text-stone-700 hover:text-stone-500 dark:hover:text-stone-500 flex-shrink-0 cursor-grab mt-0.5" style="width:.9rem;height:.9rem;" title="Drag to reorder"><?= $dragHandleIcon ?></span>
                <?php endif; ?>
            </div>
            <?php if ($meta !== null): ?>
                <p class="text-xs text-stone-500 dark:text-stone-400 mb-3"><?= View::e($meta) ?></p>
            <?php endif; ?>

            <?php if ($children !== []): ?>
                <div class="space-y-0.5 mb-3 border-t border-stone-100 dark:border-stone-800 pt-1">
                    <?php foreach ($children as $child): ?>
                        <?php $renderChildRow($child, $groups, $parentOptions); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="button" data-modal-open="<?= View::e($modalId) ?>" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Manage</button>
        </div>
    </div>
    <?php $modalStart($modalId, ($isArchived ? 'Archived: ' : 'Manage "') . $category['name'] . ($isArchived ? '' : '"')); ?>
        <?php $renderManageForm($category, $groups, $parentOptions); ?>
        <?php $renderDangerZone($category, $isArchived); ?>
    <?php $modalEnd(); ?>
    <?php
};

$renderSections = function (array $sections, array $groups, array $parentOptions) use ($csrfToken, $renderCatCard): void {
    foreach ($sections as $section) {
        $group = $section['group'];
        ?>
        <div class="card">
            <div class="flex items-center justify-between mb-4">
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
                    <div class="category-group-list grid grid-cols-1 sm:grid-cols-2 gap-3" data-group-id="<?= $group !== null ? (int) $group['id'] : 0 ?>">
                        <?php foreach ($section['active'] as $row): ?>
                            <?php $renderCatCard($row['category'], $row['children'], $groups, $parentOptions, false); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($section['archived'] !== []): ?>
                    <details class="mt-4 border-t border-stone-100 dark:border-stone-800 pt-3">
                        <summary class="cursor-pointer text-sm text-stone-500 dark:text-stone-400 hover:underline list-none"><?= count($section['archived']) ?> archived</summary>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <?php foreach ($section['archived'] as $row): ?>
                                <?php $renderCatCard($row['category'], $row['children'], $groups, $parentOptions, true); ?>
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
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Changes here apply everywhere categories are used — transactions, budgets, and reports. Drag a card to reorder it within its section, or open "Manage" on any card to rename it, move it, nest it, merge it, or archive it. Subcategories live inside their parent's card — click one to manage it directly.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div>
                    <button type="button" data-modal-open="new-category-modal" class="btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" style="width:.9rem;height:.9rem;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        New category
                    </button>
                </div>

                <?php $modalStart('new-category-modal', 'New category'); ?>
                    <form method="POST" action="/settings/categories" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <label for="name" class="field-label">Name</label>
                                <input type="text" id="name" name="name" required value="<?= $editingId === null ? View::e($old['name'] ?? '') : '' ?>" class="field-input">
                            </div>
                            <div>
                                <label for="color" class="field-label">Color</label>
                                <input type="color" id="color" name="color" value="<?= $editingId === null ? View::e($old['color'] ?? '#a8a29e') : '#a8a29e' ?>" class="field-color">
                            </div>
                        </div>
                        <div>
                            <label for="type" class="field-label">Type</label>
                            <select id="type" name="type" class="field-input">
                                <option value="expense" <?= ($editingId === null ? ($old['type'] ?? 'expense') : 'expense') === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="income" <?= $editingId === null && ($old['type'] ?? '') === 'income' ? 'selected' : '' ?>>Income</option>
                            </select>
                        </div>
                        <div>
                            <label for="group_id" class="field-label">Section (optional)</label>
                            <select id="group_id" name="group_id" class="field-input">
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
                            <select id="parent_category_id" name="parent_category_id" class="field-input">
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
                        <button type="submit" class="btn-primary btn-block">Create category</button>
                    </form>
                <?php $modalEnd(); ?>

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

    <script src="<?= View::asset('/assets/js/modals.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/settings-categories.js') ?>" defer></script>
</body>
</html>
