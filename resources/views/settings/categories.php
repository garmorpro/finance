<?php

/** @var array $expenseCategories */
/** @var array $incomeCategories */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $old */

use App\Support\View;

$editingId = $old['id'] ?? null;

$renderCategoryRow = function (array $category) use ($csrfToken, $editingId, $old): void {
    $isEditing = $editingId !== null && (string) $category['id'] === (string) $editingId;
    $name = $isEditing ? ($old['name'] ?? $category['name']) : $category['name'];
    $color = $isEditing ? ($old['color'] ?? $category['color']) : $category['color'];
    $isArchived = $category['archived_at'] !== null;
    ?>
    <tr class="<?= $isArchived ? 'opacity-50' : '' ?>">
        <td>
            <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="type" value="<?= View::e($category['type']) ?>">
                <input type="color" name="color" value="<?= View::e($color ?? '#e2694b') ?>" class="field-input h-8 w-10 p-0.5">
                <input type="text" name="name" value="<?= View::e($name) ?>" class="field-input" style="max-width: 14rem;">
                <button type="submit" class="btn-secondary">Save</button>
            </form>
        </td>
        <td class="text-right">
            <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>/<?= $isArchived ? 'restore' : 'archive' ?>">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn-secondary"><?= $isArchived ? 'Restore' : 'Archive' ?></button>
            </form>
        </td>
    </tr>
    <?php
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Categories</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Manage the categories used for transactions.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Add category</h2>
                    <form method="POST" action="/settings/categories" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div>
                            <label for="color" class="field-label">Color</label>
                            <input type="color" id="color" name="color" value="<?= $editingId === null ? View::e($old['color'] ?? '#e2694b') : '#e2694b' ?>" class="field-input h-10 w-14 p-0.5">
                        </div>
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
                        <button type="submit" class="btn-primary">Add</button>
                    </form>
                </div>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Expense categories</h2>
                    <table class="table-base">
                        <tbody>
                            <?php foreach ($expenseCategories as $category): ?>
                                <?php $renderCategoryRow($category); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Income categories</h2>
                    <table class="table-base">
                        <tbody>
                            <?php foreach ($incomeCategories as $category): ?>
                                <?php $renderCategoryRow($category); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
