<?php

/** @var array $accounts */
/** @var array $recentImports */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import transactions · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Import from CSV</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <?php if ($accounts === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400 mb-2">You need at least one account before importing transactions.</p>
                        <a href="/accounts/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add an account &rarr;</a>
                    </div>
                <?php else: ?>
                <form method="POST" action="/transactions/import" enctype="multipart/form-data" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <label for="account_id" class="field-label">Import into account</label>
                        <select id="account_id" name="account_id" required class="field-input">
                            <option value="">Select an account&hellip;</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"><?= View::e($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-help">Every row in the file will be imported as a transaction on this account.</p>
                    </div>

                    <div>
                        <label for="csv_file" class="field-label">CSV file</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required class="field-input">
                        <p class="field-help">Max 5MB. The next step lets you preview the data and map columns before anything is imported.</p>
                    </div>

                    <button type="submit" class="btn-primary">Continue</button>
                </form>
                <?php endif; ?>

                <?php if ($recentImports !== []): ?>
                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-4">Recent imports</h2>
                    <table class="table-base">
                        <thead>
                            <tr><th>Date</th><th>File</th><th>Account</th><th>By</th><th class="text-right">Imported</th><th class="text-right">Skipped</th><th class="text-right">Rejected</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentImports as $import): ?>
                                <tr>
                                    <td class="text-stone-500 dark:text-stone-400"><?= View::e($import['created_at']) ?></td>
                                    <td class="text-stone-900 dark:text-white"><?= View::e($import['filename']) ?></td>
                                    <td class="text-stone-500 dark:text-stone-400"><?= View::e($import['account_name']) ?></td>
                                    <td class="text-stone-500 dark:text-stone-400"><?= View::e($import['imported_by_name']) ?></td>
                                    <td class="text-right text-emerald-700 dark:text-emerald-400"><?= (int) $import['imported_count'] ?></td>
                                    <td class="text-right text-stone-500 dark:text-stone-400"><?= (int) $import['skipped_count'] ?></td>
                                    <td class="text-right text-red-600 dark:text-red-400"><?= (int) $import['rejected_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
