<?php

/** @var array $transaction */
/** @var array|null $sourceAccount */
/** @var string|null $pairAccountName */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\Money;
use App\Support\View;

$isOutgoing = $transaction['transaction_type'] === 'transfer' && str_starts_with($transaction['amount'], '-');
$accountColor = ($sourceAccount['color'] ?? null) ?: '#a8a29e';
$transferIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit transfer · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <div class="account-card" style="background-color: <?= View::e($accountColor) ?>;">
                    <div class="flex items-start justify-between">
                        <span class="account-card-icon"><?= $transferIcon ?></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-white/70">Transfer</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-lg font-semibold truncate"><?= $isOutgoing ? 'To' : 'From' ?> <?= View::e($pairAccountName ?? 'another account') ?></div>
                        <?php if ($sourceAccount !== null): ?>
                            <div class="text-sm text-white/70 truncate"><?= View::e($sourceAccount['name']) ?> &middot; <?= View::e($transaction['transaction_date']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-end justify-between mt-2">
                        <span class="text-lg tracking-widest text-white/60" aria-hidden="true">&bull;&bull;&bull;&bull;</span>
                        <div class="text-right">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-white/60">Amount</div>
                            <div class="text-2xl font-semibold"><?= Money::format($transaction['amount']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <p class="text-sm text-stone-500 dark:text-stone-400">
                        The amount, date, and accounts on a transfer can't be edited directly — delete it below and create a new one if those need to change. This keeps both sides of the transfer (and both account balances) in sync.
                    </p>
                </div>

                <form method="POST" action="/transactions/<?= (int) $transaction['id'] ?>" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div>
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($transaction['notes'] ?? '') ?></textarea>
                    </div>

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
