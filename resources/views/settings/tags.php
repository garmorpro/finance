<?php

/** @var array $tags */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */
/** @var array $old */

use App\Support\SettingsIcons;
use App\Support\View;

$editingId = $old['id'] ?? null;

require __DIR__ . '/../partials/_modal_shell.php';

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
                            <div class="flex items-center gap-2.5">
                                <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('tags') ?></span>
                                <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Tags</h2>
                            </div>
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
                                    <input type="color" id="color" name="color" value="<?= $editingId === null ? View::e($old['color'] ?? '#e2694b') : '#e2694b' ?>" class="field-color">
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
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($tags as $tag): ?>
                                        <?php
                                        $tagId = (int) $tag['id'];
                                        $modalId = 'manage-tag-modal-' . $tagId;
                                        $color = $tag['color'] ?: '#e2694b';
                                        $usageCount = (int) $tag['usage_count'];
                                        ?>
                                        <button type="button" data-modal-open="<?= View::e($modalId) ?>" class="inline-flex items-center gap-2 pl-2.5 pr-3 py-1.5 rounded-full border border-stone-200 dark:border-stone-800 bg-stone-50 dark:bg-stone-800/60 hover:bg-stone-100 dark:hover:bg-stone-800 text-sm">
                                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: <?= View::e($color) ?>"></span>
                                            <span class="font-medium text-stone-900 dark:text-white"><?= View::e($tag['name']) ?></span>
                                            <?php if ($usageCount > 0): ?>
                                                <span class="text-xs text-stone-500 dark:text-stone-400"><?= $usageCount ?></span>
                                            <?php endif; ?>
                                        </button>

                                        <?php $modalStart($modalId, 'Manage "' . $tag['name'] . '"'); ?>
                                            <form method="POST" action="/settings/tags/<?= $tagId ?>" class="space-y-2.5">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <div class="flex items-end gap-3">
                                                    <div class="flex-1">
                                                        <label class="field-label">Name</label>
                                                        <input type="text" name="name" required maxlength="50" value="<?= View::e($tag['name']) ?>" class="field-input">
                                                    </div>
                                                    <div>
                                                        <label class="field-label">Color</label>
                                                        <input type="color" name="color" value="<?= View::e($color) ?>" class="field-color">
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn-secondary btn-sm">Save</button>
                                            </form>
                                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-3">Used on <?= $usageCount ?> transaction<?= $usageCount === 1 ? '' : 's' ?>.</p>
                                            <div class="flex items-center justify-between gap-2 mt-3 pt-3 border-t border-stone-100 dark:border-stone-800">
                                                <span class="text-[11px] font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-600">Danger zone</span>
                                                <form method="POST" action="/settings/tags/<?= $tagId ?>/delete" onsubmit="return confirm('Delete the \'<?= View::e($tag['name']) ?>\' tag? It will be removed from every transaction that has it.');">
                                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                    <button type="submit" class="btn-secondary btn-sm text-red-600 dark:text-red-400">Delete</button>
                                                </form>
                                            </div>
                                        <?php $modalEnd(); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

</body>
</html>
