<?php

/** @var list<array> $rules */
/** @var int $ruleCount */
/** @var int $activeRuleCount */
/** @var int $pausedRuleCount */
/** @var list<array{id: int, name: string, type: string, color: string|null}> $categories */
/** @var int $categoryCount */
/** @var int $incomeCategoryCount */
/** @var int $expenseCategoryCount */
/** @var list<array{id: int, name: string, color: string|null}> $tags */
/** @var int $tagCount */
/** @var string $csrfToken */

use App\Support\View;

$zapIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
$gridIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
$tagIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rules &amp; Inputs · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'rules']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Rules &amp; Inputs</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Automation, categories, and tags — the building blocks that shape how your transactions get organized.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $zapIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Rules</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $activeRuleCount ?> active</div>
                        <?php if ($pausedRuleCount > 0): ?>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1"><?= $pausedRuleCount ?> paused</p>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $gridIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Categories</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $categoryCount ?> total</div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1"><?= $incomeCategoryCount ?> income &middot; <?= $expenseCategoryCount ?> expense</p>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $tagIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Tags</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $tagCount ?> total</div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Freeform labels</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="card">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-stone-900 dark:text-white">Rules</h2>
                            <a href="/settings/rules" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all &rarr;</a>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Automatically categorize, rename, tag, or hide transactions as they come in.</p>

                        <?php if ($rules === []): ?>
                            <div class="tile-placeholder">
                                <p class="text-sm mb-2">No rules yet.</p>
                                <a href="/settings/rules/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
                            </div>
                        <?php else: ?>
                            <ul class="space-y-3">
                                <?php foreach ($rules as $rule): ?>
                                    <?php $isActive = (int) $rule['is_active'] === 1; ?>
                                    <li class="flex items-center justify-between text-sm gap-2">
                                        <span class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($rule['name']) ?></span>
                                        <span class="badge flex-shrink-0 <?= $isActive ? '' : 'opacity-60' ?>"><?= $isActive ? 'Active' : 'Paused' ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-stone-900 dark:text-white">Categories</h2>
                            <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all &rarr;</a>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Income and expense categories, grouped into sections.</p>

                        <?php if ($categories === []): ?>
                            <div class="tile-placeholder">
                                <p class="text-sm mb-2">No categories yet.</p>
                                <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
                            </div>
                        <?php else: ?>
                            <ul class="space-y-3">
                                <?php foreach ($categories as $category): ?>
                                    <li class="flex items-center gap-2.5 text-sm min-w-0">
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($category['color'] ?: '#a8a29e') ?>"></span>
                                        <span class="text-stone-700 dark:text-stone-300 truncate"><?= View::e($category['name']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="font-semibold text-stone-900 dark:text-white">Tags</h2>
                            <a href="/settings/tags" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">View all &rarr;</a>
                        </div>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Freeform labels, independent of category.</p>

                        <?php if ($tags === []): ?>
                            <div class="tile-placeholder">
                                <p class="text-sm mb-2">No tags yet.</p>
                                <a href="/settings/tags" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge"><?= View::e($tag['name']) ?></span>
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
