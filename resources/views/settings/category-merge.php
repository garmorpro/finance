<?php

/** @var array $category */
/** @var array $otherCategories */
/** @var array|null $target */
/** @var string|null $targetError */
/** @var array{transactions: int, splits: int, recurringItems: int, ruleActions: int, budgetItems: int, subcategories: int}|null $preview */
/** @var string $csrfToken */

use App\Support\View;

$previewLines = [];
if ($preview !== null) {
    if ($preview['transactions'] > 0) {
        $previewLines[] = $preview['transactions'] . ' transaction' . ($preview['transactions'] === 1 ? '' : 's');
    }
    if ($preview['splits'] > 0) {
        $previewLines[] = $preview['splits'] . ' split line' . ($preview['splits'] === 1 ? '' : 's') . ' inside other transactions';
    }
    if ($preview['recurringItems'] > 0) {
        $previewLines[] = $preview['recurringItems'] . ' recurring item' . ($preview['recurringItems'] === 1 ? '' : 's');
    }
    if ($preview['budgetItems'] > 0) {
        $previewLines[] = $preview['budgetItems'] . ' budget line' . ($preview['budgetItems'] === 1 ? '' : 's') . ' (amounts already planned for the same month under both categories will be added together)';
    }
    if ($preview['ruleActions'] > 0) {
        $previewLines[] = $preview['ruleActions'] . ' rule' . ($preview['ruleActions'] === 1 ? '' : 's') . ' that set this category';
    }
    if ($preview['subcategories'] > 0) {
        $previewLines[] = $preview['subcategories'] . ' subcategor' . ($preview['subcategories'] === 1 ? 'y' : 'ies') . ', which will move under "' . $target['name'] . '" instead';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merge "<?= View::e($category['name']) ?>" · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main">
                <a href="/settings/categories" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Categories</a>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Merge "<?= View::e($category['name']) ?>"</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Moves everything from this category into another, then archives this one. This can't be undone automatically.</p>

                <?php if ($targetError !== null): ?>
                    <div class="alert-error"><?= View::e($targetError) ?></div>
                <?php endif; ?>

                <?php if ($otherCategories === []): ?>
                    <div class="card text-center py-10">
                        <p class="text-stone-500 dark:text-stone-400">There's no other <?= View::e($category['type']) ?> category to merge this into yet.</p>
                    </div>
                <?php else: ?>
                    <form method="GET" action="/settings/categories/<?= (int) $category['id'] ?>/merge" class="card flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[12rem]">
                            <label for="target_id" class="field-label">Merge into</label>
                            <select id="target_id" name="target_id" class="field-input" required>
                                <option value="">Choose a category&hellip;</option>
                                <?php foreach ($otherCategories as $other): ?>
                                    <option value="<?= (int) $other['id'] ?>" <?= $target !== null && (int) $target['id'] === (int) $other['id'] ? 'selected' : '' ?>><?= View::e($other['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-secondary">Preview merge</button>
                    </form>

                    <?php if ($target !== null && $preview !== null): ?>
                        <div class="card">
                            <h2 class="font-medium text-stone-900 dark:text-white mb-3">This will move:</h2>
                            <?php if ($previewLines === []): ?>
                                <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">Nothing — this category isn't used anywhere yet.</p>
                            <?php else: ?>
                                <ul class="text-sm text-stone-700 dark:text-stone-300 space-y-1.5 mb-4 list-disc pl-5">
                                    <?php foreach ($previewLines as $line): ?>
                                        <li><?= View::e($line) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">"<?= View::e($category['name']) ?>" will then be archived — its history stays intact, it just won't show up as a category to pick from anymore.</p>
                            <form method="POST" action="/settings/categories/<?= (int) $category['id'] ?>/merge" onsubmit="return confirm('Merge &quot;<?= View::e($category['name']) ?>&quot; into &quot;<?= View::e($target['name']) ?>&quot;? This can\'t be undone automatically.');">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                                <button type="submit" class="btn-primary">Merge into "<?= View::e($target['name']) ?>"</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
