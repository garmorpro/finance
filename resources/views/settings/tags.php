<?php

/** @var array $tags */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $old */

use App\Support\View;

$editingId = $old['id'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tags · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'tags']); ?>

                    <div class="space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Tags</h2>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Free-form labels you can attach to any transaction, independent of category.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert-error"><?= View::e($error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($notice)): ?>
                            <div class="alert-success"><?= View::e($notice) ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <h3 class="font-medium text-stone-900 dark:text-white mb-4">Add tag</h3>
                            <form method="POST" action="/settings/tags" class="flex flex-wrap items-end gap-3">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <div>
                                    <label for="color" class="field-label">Color</label>
                                    <input type="color" id="color" name="color" value="<?= $editingId === null ? View::e($old['color'] ?? '#e2694b') : '#e2694b' ?>" class="field-input h-10 w-14 p-0.5">
                                </div>
                                <div class="flex-1 min-w-[10rem]">
                                    <label for="name" class="field-label">Name</label>
                                    <input type="text" id="name" name="name" required maxlength="50" value="<?= $editingId === null ? View::e($old['name'] ?? '') : '' ?>" class="field-input">
                                </div>
                                <button type="submit" class="btn-primary">Add</button>
                            </form>
                        </div>

                        <?php if ($tags === []): ?>
                            <div class="card text-center py-10">
                                <p class="text-stone-500 dark:text-stone-400">No tags yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <table class="table-base">
                                    <thead>
                                        <tr><th>Tag</th><th>Used on</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tags as $tag): ?>
                                            <?php
                                            $isEditing = $editingId !== null && (string) $tag['id'] === (string) $editingId;
                                            $name = $isEditing ? ($old['name'] ?? $tag['name']) : $tag['name'];
                                            $color = $isEditing ? ($old['color'] ?? $tag['color']) : $tag['color'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <form method="POST" action="/settings/tags/<?= (int) $tag['id'] ?>" class="flex items-center gap-2">
                                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                        <input type="color" name="color" value="<?= View::e($color ?? '#e2694b') ?>" class="field-input h-8 w-10 p-0.5">
                                                        <input type="text" name="name" required maxlength="50" value="<?= View::e($name) ?>" class="field-input" style="max-width: 14rem;">
                                                        <button type="submit" class="btn-secondary">Save</button>
                                                    </form>
                                                </td>
                                                <td class="text-stone-500 dark:text-stone-400"><?= (int) $tag['usage_count'] ?> transaction<?= (int) $tag['usage_count'] === 1 ? '' : 's' ?></td>
                                                <td class="text-right">
                                                    <form method="POST" action="/settings/tags/<?= (int) $tag['id'] ?>/delete" onsubmit="return confirm('Delete the \'<?= View::e($tag['name']) ?>\' tag? It will be removed from every transaction that has it.');">
                                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                        <button type="submit" class="btn-secondary">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
