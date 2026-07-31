<?php

/** @var string $token */
/** @var string $originalName */
/** @var int $accountId */
/** @var list<string> $header */
/** @var list<list<string>> $previewRows */
/** @var int $totalRows */
/** @var string $csrfToken */

use App\Support\View;

$columnSelect = function (string $name, string $label, bool $required = true) use ($header): void {
    ?>
    <div>
        <label for="<?= $name ?>" class="field-label"><?= View::e($label) ?><?= $required ? '' : ' (optional)' ?></label>
        <select id="<?= $name ?>" name="<?= $name ?>" class="field-input" <?= $required ? 'required' : '' ?>>
            <?php if (!$required): ?>
                <option value="">Don't import</option>
            <?php else: ?>
                <option value="">Select a column&hellip;</option>
            <?php endif; ?>
            <?php foreach ($header as $index => $columnName): ?>
                <option value="<?= $index ?>"><?= View::e($columnName !== '' ? $columnName : 'Column ' . ($index + 1)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Map columns · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div>
                    <a href="/transactions/import" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Start over</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Map columns</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1"><?= View::e($originalName) ?> — <?= $totalRows ?> row<?= $totalRows === 1 ? '' : 's' ?> found</p>
                </div>

                <form method="POST" action="/transactions/import/confirm" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <input type="hidden" name="token" value="<?= View::e($token) ?>">
                    <input type="hidden" name="original_name" value="<?= View::e($originalName) ?>">
                    <input type="hidden" name="account_id" value="<?= $accountId ?>">

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <?php $columnSelect('date_column', 'Date'); ?>
                        <?php $columnSelect('amount_column', 'Amount'); ?>
                        <?php $columnSelect('payee_column', 'Payee'); ?>
                        <?php $columnSelect('category_column', 'Category', false); ?>
                        <?php $columnSelect('notes_column', 'Notes', false); ?>
                    </div>

                    <p class="field-help">
                        Amount should be a single column where negative means money out and positive means money in
                        (how most bank exports work). If yours is reversed, check the box below.
                    </p>

                    <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                        <input type="checkbox" name="flip_sign" value="1" class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                        Flip amount sign (my file has expenses as positive numbers)
                    </label>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Import <?= $totalRows ?> row<?= $totalRows === 1 ? '' : 's' ?></button>
                        <a href="/transactions/import" class="btn-secondary">Cancel</a>
                    </div>
                </form>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Preview (first <?= count($previewRows) ?> rows)</h2>
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <?php foreach ($header as $index => $columnName): ?>
                                        <th><?= View::e($columnName !== '' ? $columnName : 'Column ' . ($index + 1)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewRows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td class="text-stone-500 dark:text-stone-400 whitespace-nowrap"><?= View::e($cell) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
