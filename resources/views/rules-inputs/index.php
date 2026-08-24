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
                <div class="dash-hero">
                    <div class="dash-hero-top">
                        <div>
                            <h1>Rules &amp; Inputs</h1>
                            <p class="sub">Automation, categories, and tags — the building blocks that shape how your transactions get organized.</p>
                        </div>
                        <div class="dash-hero-actions">
                            <a href="/settings/rules/create" class="btn-ghost-dark">Add rule</a>
                            <a href="/settings/categories" class="btn-primary">Manage categories</a>
                        </div>
                    </div>

                    <div class="dash-hero-body">
                        <div class="dash-hero-nw">
                            <p class="dash-hero-eyebrow">Rules</p>
                            <div class="dash-hero-nw-value tabular-nums"><?= $activeRuleCount ?> active</div>
                            <p class="dash-hero-nw-caption" style="margin-top:0.85rem; max-width:42ch;"><?= $pausedRuleCount > 0 ? $pausedRuleCount . ' paused.' : 'None paused.' ?> Automatically categorize, rename, tag, or hide transactions as they come in.</p>
                        </div>

                        <div class="dash-hero-stats">
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon"><?= $gridIcon ?></span>
                                <span>
                                    <span class="dash-hero-stat-label">Categories &middot; <?= $incomeCategoryCount ?> income, <?= $expenseCategoryCount ?> expense</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= $categoryCount ?> total</span>
                                </span>
                            </div>
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon"><?= $tagIcon ?></span>
                                <span>
                                    <span class="dash-hero-stat-label">Tags &middot; Freeform labels</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= $tagCount ?> total</span>
                                </span>
                            </div>
                        </div>
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
