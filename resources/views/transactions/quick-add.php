<?php

/** @var array<int, array<string, mixed>> $accounts */
/** @var array<int, array<string, mixed>> $categories */
/** @var int|null $defaultAccountId */
/** @var bool $isKeyAuth */
/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <?php View::partial('partials/_pwa_head'); ?>
    <title>Quick add · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body class="quickadd-page">
    <?php if ($isKeyAuth): ?>
        <form method="POST" action="/quick-add/forget" class="absolute left-4" style="top: calc(1rem + env(safe-area-inset-top));" onsubmit="return confirm('Forget this device? You\'ll need to enter your Quick Add key again to use this page here.');">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="text-xs font-semibold text-stone-400 dark:text-stone-600 hover:text-stone-600 dark:hover:text-stone-300 transition-colors">Forget this device</button>
        </form>
    <?php else: ?>
        <a href="/" class="absolute left-4 text-xs font-semibold text-stone-400 dark:text-stone-600 hover:text-stone-600 dark:hover:text-stone-300 transition-colors" style="top: calc(1rem + env(safe-area-inset-top));">
            &larr; Dashboard
        </a>
    <?php endif; ?>

    <div class="quickadd-modal">
        <div id="quick-add-success" class="quickadd-success-overlay hidden" role="status" aria-live="polite">
            <span class="quickadd-success-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:1.75rem;height:1.75rem;" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <p class="text-base font-bold text-stone-900 dark:text-white">Added!</p>
        </div>

        <div class="flex items-start justify-between gap-4 mb-1">
            <div class="flex items-center gap-3">
                <span class="quickadd-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="width:1.2rem;height:1.2rem;" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </span>
                <div>
                    <h1 class="text-[1.0625rem] font-bold tracking-tight text-stone-900 dark:text-white">Quick add</h1>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">Log a transaction in seconds</p>
                </div>
            </div>
        </div>

        <div class="quickadd-rule"></div>

        <p id="quick-add-error" class="alert-error mb-4 hidden"></p>

        <form id="quick-add-page-form" method="POST" action="/quick-add">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" id="quick-add-type" name="transaction_type" value="expense">

            <div class="quickadd-type-toggle" role="group" aria-label="Transaction type">
                <button type="button" id="quick-add-type-expense" class="quickadd-type-btn" data-type="expense" aria-pressed="true">Expense</button>
                <button type="button" id="quick-add-type-income" class="quickadd-type-btn" data-type="income" aria-pressed="false">Income</button>
            </div>

            <div class="quickadd-amount-box">
                <p class="quickadd-amount-eyebrow">Amount</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-semibold text-stone-500 dark:text-stone-400">$</span>
                    <input type="text" inputmode="decimal" id="quick-add-amount" name="amount" required placeholder="0.00" class="quickadd-amount-input">
                </div>
            </div>

            <div class="quickadd-field">
                <label for="quick-add-payee" class="quickadd-field-label">Payee</label>
                <div class="quickadd-input-shell">
                    <svg class="quickadd-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1z"/><path d="M9 21v-6h6v6"/></svg>
                    <input type="text" id="quick-add-payee" name="payee" required class="quickadd-input" placeholder="e.g. Whole Foods">
                </div>
                <p id="quick-add-payee-help" class="field-help">Who you paid, or who paid you — the merchant, company, or person on the other side of this transaction.</p>
            </div>

            <div class="quickadd-field">
                <label for="quick-add-account" class="quickadd-field-label">Account</label>
                <div class="quickadd-input-shell">
                    <svg class="quickadd-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                    <select id="quick-add-account" name="account_id" required class="quickadd-select" data-swatch-target="quick-add-account-swatch">
                        <option value="">Select an account&hellip;</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>" data-color="<?= View::e($account['color'] ?: '#a8a29e') ?>" <?= $defaultAccountId !== null && (int) $account['id'] === $defaultAccountId ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="quick-add-account-swatch" class="quickadd-swatch" aria-hidden="true"></span>
                </div>
            </div>

            <div class="quickadd-field">
                <label for="quick-add-category" class="quickadd-field-label">Category <span class="normal-case font-medium tracking-normal">(optional)</span></label>
                <div class="quickadd-input-shell">
                    <svg class="quickadd-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/></svg>
                    <select id="quick-add-category" name="category_id" class="quickadd-select" data-swatch-target="quick-add-category-swatch">
                        <option value="">No category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" data-color="<?= View::e($category['color'] ?: '#a8a29e') ?>" data-type="<?= View::e($category['type']) ?>" <?= $category['type'] !== 'expense' ? 'hidden' : '' ?>><?= View::e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="quick-add-category-swatch" class="quickadd-swatch" aria-hidden="true"></span>
                </div>
            </div>

            <button type="submit" id="quick-add-page-submit" class="quickadd-cta">Add transaction</button>
        </form>

        <p class="quickadd-footnote">Need to split it or add notes? Use the <a href="/transactions/create">full form</a> instead.</p>
    </div>

    <script src="<?= View::asset('/assets/js/quick-add.js') ?>" defer></script>
</body>
</html>
