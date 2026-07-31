<?php

/** @var array $item */
/** @var string $csrfToken */
/** @var string|null $error */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mark paid · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'recurring']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/recurring" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Recurring</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Mark "<?= View::e($item['name']) ?>" paid</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">This creates a <?= $item['recurring_type'] === 'income' ? 'income' : 'expense' ?> transaction and moves the due date forward.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/recurring/<?= (int) $item['id'] ?>/pay" class="card space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="amount" class="field-label">Amount</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" required value="<?= View::e($item['expected_amount']) ?>" class="field-input">
                            <p class="field-help">Different from expected? This updates the expected amount for next time.</p>
                        </div>
                        <div>
                            <label for="paid_date" class="field-label">Date</label>
                            <input type="date" id="paid_date" name="paid_date" required value="<?= View::e($item['next_due_date']) ?>" class="field-input">
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary">Mark paid</button>
                        <a href="/recurring" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
