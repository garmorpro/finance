<?php

/** @var array $accounts */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var array $old */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer money · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'transactions']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/transactions" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Transactions</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Transfer money</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Between your own accounts — never counted as income or spending.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/transactions/transfer" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="from_account_id" class="field-label">From</label>
                            <select id="from_account_id" name="from_account_id" required class="field-input">
                                <option value="">Select an account&hellip;</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === ($old['from_account_id'] ?? '') ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="to_account_id" class="field-label">To</label>
                            <select id="to_account_id" name="to_account_id" required class="field-input">
                                <option value="">Select an account&hellip;</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= (string) $account['id'] === ($old['to_account_id'] ?? '') ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="field-label">Amount</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" required value="<?= View::e($old['amount'] ?? '') ?>" class="field-input" placeholder="0.00">
                        </div>
                        <div>
                            <label for="transaction_date" class="field-label">Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" required value="<?= View::e($old['transaction_date'] ?? date('Y-m-d')) ?>" class="field-input">
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="field-label">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="field-input"><?= View::e($old['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Transfer</button>
                        <a href="/transactions" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
