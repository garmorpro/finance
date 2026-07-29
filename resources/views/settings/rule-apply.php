<?php

/** @var array $rule */
/** @var int $count */
/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply rule · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main">
                <div>
                    <a href="/settings/rules" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">&larr; Rules</a>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mt-1">Apply "<?= View::e($rule['name']) ?>" to existing transactions</h1>
                </div>

                <div class="card space-y-4">
                    <?php if ($count === 0): ?>
                        <p class="text-stone-700 dark:text-stone-300">No existing transactions currently match this rule's conditions. Nothing to apply.</p>
                        <a href="/settings/rules" class="btn-secondary">Back to Rules</a>
                    <?php else: ?>
                        <p class="text-stone-700 dark:text-stone-300">
                            This will affect <span class="font-semibold"><?= $count ?></span> existing transaction<?= $count === 1 ? '' : 's' ?> — the rule's actions will be applied to each one now. This cannot be undone automatically; you'd need to edit affected transactions back by hand.
                        </p>
                        <form method="POST" action="/settings/rules/<?= (int) $rule['id'] ?>/apply" onsubmit="return confirm('Apply this rule to <?= $count ?> existing transaction<?= $count === 1 ? '' : 's' ?> now?');" class="flex gap-3">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button type="submit" class="btn-primary">Yes, apply to <?= $count ?> transaction<?= $count === 1 ? '' : 's' ?></button>
                            <a href="/settings/rules" class="btn-secondary">Cancel</a>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
