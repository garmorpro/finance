<?php

/** @var array $accounts */
/** @var string $csrfToken */
/** @var string|null $notice */

use App\Support\AccountTypeLabels;
use App\Support\Money;
use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="page-shell">
    <?php View::partial('partials/nav', ['csrfToken' => $csrfToken, 'active' => 'accounts']); ?>

    <main class="page-main">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Accounts</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manually tracked balances for your household.</p>
            </div>
            <a href="/accounts/create" class="btn-primary">Add account</a>
        </div>

        <?php if (!empty($notice)): ?>
            <div class="alert-success"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <?php if ($accounts === []): ?>
            <div class="card text-center py-12">
                <p class="text-slate-500 dark:text-slate-400">No accounts yet.</p>
                <a href="/accounts/create" class="inline-block mt-3 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Add your first account &rarr;</a>
            </div>
        <?php else: ?>
            <div class="card">
                <table class="table-base">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Institution</th><th class="text-right">Balance</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr class="<?= $account['status'] === 'archived' ? 'opacity-50' : '' ?>">
                                <td class="font-medium text-slate-900 dark:text-white">
                                    <?php if (!empty($account['color'])): ?>
                                        <span class="inline-block w-2 h-2 rounded-full mr-2" style="background-color: <?= View::e($account['color']) ?>"></span>
                                    <?php endif; ?>
                                    <?= View::e($account['name']) ?>
                                    <?php if ($account['status'] === 'archived'): ?>
                                        <span class="badge ml-2">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= View::e(AccountTypeLabels::label($account['account_type'])) ?></td>
                                <td class="text-slate-500 dark:text-slate-400"><?= View::e($account['institution_name'] ?? '—') ?></td>
                                <td class="text-right font-medium text-slate-900 dark:text-white"><?= Money::format($account['current_balance']) ?></td>
                                <td class="text-right">
                                    <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
