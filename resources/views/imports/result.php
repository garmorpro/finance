<?php

/** @var int $imported */
/** @var int $skipped */
/** @var int $rejected */
/** @var int $total */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Import complete · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="auth-shell">
    <div class="auth-card text-center">
        <h1 class="text-xl font-semibold text-stone-900 dark:text-white mb-4">Import complete</h1>

        <div class="grid grid-cols-3 gap-3 mb-4">
            <div>
                <div class="text-2xl font-semibold text-emerald-700 dark:text-emerald-400"><?= $imported ?></div>
                <div class="label-text">Imported</div>
            </div>
            <div>
                <div class="text-2xl font-semibold text-stone-500 dark:text-stone-400"><?= $skipped ?></div>
                <div class="label-text">Skipped</div>
            </div>
            <div>
                <div class="text-2xl font-semibold text-red-600 dark:text-red-400"><?= $rejected ?></div>
                <div class="label-text">Rejected</div>
            </div>
        </div>

        <p class="text-sm text-stone-500 dark:text-stone-400 mb-6">
            <?= $skipped ?> row<?= $skipped === 1 ? '' : 's' ?> matched an existing transaction and were skipped.
            <?= $rejected ?> row<?= $rejected === 1 ? '' : 's' ?> couldn't be read (missing or invalid date/amount/payee).
        </p>

        <a href="/transactions" class="btn-primary">View transactions</a>
    </div>
</body>
</html>
