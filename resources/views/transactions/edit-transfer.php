<?php

/** @var array $transaction */
/** @var string|null $pairAccountName */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\Money;
use App\Support\View;

$isOutgoing = $transaction['transaction_type'] === 'transfer' && str_starts_with($transaction['amount'], '-');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit transfer · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Transfer</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <div class="card">
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">
                        The amount, date, and accounts on a transfer can't be edited directly — delete it below and create a new one if those need to change. This keeps both sides of the transfer (and both account balances) in sync.
                    </p>
                    <div class="grid grid-cols-2 gap-4 text-sm mb-2">
                        <div>
                            <div class="label-text">Direction</div>
                            <div class="text-stone-900 dark:text-white"><?= $isOutgoing ? 'To' : 'From' ?> <?= View::e($pairAccountName ?? 'another account') ?></div>
                        </div>
                        <div>
                            <div class="label-text">Amount</div>
                            <div class="text-stone-900 dark:text-white"><?= Money::format($transaction['amount']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Date</div>
                            <div class="text-stone-900 dark:text-white"><?= View::e($transaction['transaction_date']) ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($transaction['notes'] ?? '') ?></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                        <input type="checkbox" name="is_reviewed" value="1" <?= (int) $transaction['is_reviewed'] === 1 ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
                        Reviewed
                    </label>

                    <button type="submit" class="btn-primary">Save changes</button>
                </form>

                <div class="card">
                    <h2 class="font-medium text-stone-900 dark:text-white mb-2">Delete transfer</h2>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">This removes both sides and reverses the effect on both account balances.</p>
                    <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>/delete" onsubmit="return confirm('Delete this transfer? This affects both accounts.');">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <button type="submit" class="btn-secondary">Delete transfer</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
