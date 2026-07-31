<?php

/** @var string $csrfToken */
/** @var string $active */

use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Services\NotificationService;
use App\Support\View;

$active = $active ?? '';

$link = function (string $key, string $href, string $label) use ($active): string {
    $class = $key === $active ? 'sidebar-link-active' : 'sidebar-link';
    return '<a href="' . $href . '" class="' . $class . '">' . $label . '</a>';
};

$householdId = (int) AuthMiddleware::householdId();
$notificationCount = (new NotificationService())->badgeCount($householdId, (int) AuthMiddleware::userId());

// Quick-add's own data — active accounts/categories only, same defaults
// listForHousehold() already uses elsewhere for a *new* transaction (an
// edit form is the one place that also needs archived accounts, so it
// can still show a transaction's current account after the fact).
$quickAddAccounts = (new AccountRepository())->listForHousehold($householdId);
$quickAddCategories = array_values(array_filter(
    (new CategoryRepository())->listForHousehold($householdId),
    fn (array $c): bool => $c['type'] === 'expense'
));

require __DIR__ . '/_modal_shell.php';

?>
<a href="#main-content" class="skip-link">Skip to main content</a>

<header class="mobile-topbar">
    <button type="button" id="sidebar-open" class="sidebar-icon-btn cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="font-semibold text-lg tracking-tight text-stone-900 dark:text-white">MyCFO+</span>
    <span class="w-8" aria-hidden="true"></span>
</header>

<div id="sidebar-overlay" class="hidden sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand flex items-center justify-between">
        MyCFO+
        <button type="button" id="sidebar-close" class="sidebar-icon-btn lg:hidden cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Close menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="flex items-center gap-1 px-4 pb-3">
        <a href="/search" class="sidebar-icon-btn cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800 <?= $active === 'search' ? 'text-stone-900 dark:text-white bg-stone-100 dark:bg-stone-800' : '' ?>" title="Search" aria-label="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </a>
        <a href="/notifications" class="sidebar-icon-btn relative cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800 <?= $active === 'notifications' ? 'text-stone-900 dark:text-white bg-stone-100 dark:bg-stone-800' : '' ?>" title="Notifications" aria-label="Notifications<?= $notificationCount > 0 ? ', ' . $notificationCount . ' unread' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($notificationCount > 0): ?>
                <span class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-terracotta-600 text-white text-[10px] font-semibold leading-none" aria-hidden="true"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span>
            <?php endif; ?>
        </a>
        <a href="/settings/profile" class="sidebar-icon-btn cursor-pointer text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-stone-800" title="Settings" aria-label="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
        <span class="sidebar-icon-btn ml-auto" aria-hidden="true" title="Coming soon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        </span>
    </div>

    <nav class="sidebar-nav" aria-label="Main">
        <?= $link('overview', '/', 'Overview') ?>
        <?= $link('accounts', '/accounts', 'Accounts') ?>
        <?= $link('transactions', '/transactions', 'Transactions') ?>

        <?= $link('cash-flow', '/cash-flow', 'Cash Flow') ?>
        <?= $link('budgets', '/budgets', 'Budgets') ?>
        <?= $link('planning', '/planning', 'Planning') ?>
        <?= $link('debt', '/debt', 'Debts') ?>
        <?= $link('reports', '/reports', 'Reports') ?>
        <?= $link('rules', '/rules-inputs', 'Rules &amp; Inputs') ?>

        <div class="pt-2 mt-2 border-t border-stone-100 dark:border-stone-800">
            <div class="px-3 pb-1.5 pt-1 flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Business</span>
                <span class="badge-owner" style="font-size: 0.6rem; padding: 0.05rem 0.4rem;">New</span>
            </div>
            <?= $link('business-overview', '/business/overview', 'Business Overview') ?>
            <?= $link('business-transactions', '/business/transactions', 'Business Transactions') ?>
        </div>

        <div class="pt-2 mt-2 border-t border-stone-100 dark:border-stone-800">
            <?= $link('settings', '/settings/profile', 'Settings') ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="/settings/profile" class="sidebar-user block hover:bg-stone-100 dark:hover:bg-stone-800 rounded-lg py-2 <?= $active === 'settings' ? 'bg-stone-100 dark:bg-stone-800' : '' ?>">
            <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e(AuthMiddleware::userName()) ?></div>
            <div class="text-stone-500 dark:text-stone-400 text-xs truncate"><?= View::e(AuthMiddleware::userEmail()) ?></div>
        </a>
        <form method="POST" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn-secondary btn-block">Log out</button>
        </form>
    </div>
</aside>

<?php
/**
 * Quick-add: reachable from anywhere (most useful on mobile, where
 * there's no faster path to "log a transaction" than a full page
 * navigation today). Deliberately minimal — Amount, Payee, Account,
 * Category — everything else (splits, notes, tags, exclude flags)
 * stays on the full /transactions/create form, linked below. Always
 * an expense; logging income or a split still goes through the full
 * form. `redirect_to` carries the current page back through
 * TransactionController::store() so a quick add doesn't yank you to
 * the Transactions list from wherever you actually were.
 *
 * Styled deliberately more elevated than the plain popup shell every
 * other page's "Manage" uses (see the .quickadd-* classes in app.css)
 * — this one's meant to be opened many times a day, not tucked away
 * for occasional edits, so it doesn't share $modalStart()/$modalEnd().
 * Still uses modals.js's generic open/close/focus-trap via the same
 * .modal-overlay / [data-modal-open] / [data-modal-close] contract.
 */
?>
<button type="button" data-modal-open="quick-add-modal" class="quickadd-fab fixed bottom-6 right-6 z-20 w-14 h-14 rounded-full text-white flex items-center justify-center transition-transform" aria-label="Quick add transaction">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
</button>

<div id="quick-add-modal" class="modal-overlay quickadd-overlay hidden fixed inset-0 z-50 items-center justify-center px-4" role="dialog" aria-modal="true" aria-labelledby="quick-add-modal-title">
    <div class="quickadd-modal">
        <div class="flex items-start justify-between gap-4 mb-1">
            <div class="flex items-center gap-3">
                <span class="quickadd-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="width:1.2rem;height:1.2rem;" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </span>
                <div>
                    <h2 id="quick-add-modal-title" class="text-[1.0625rem] font-bold tracking-tight text-stone-900 dark:text-white">Quick add</h2>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">Log a transaction in seconds</p>
                </div>
            </div>
            <button type="button" data-modal-close="quick-add-modal" class="quickadd-close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="quickadd-rule"></div>

        <form method="POST" action="/transactions">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="transaction_type" value="expense">
            <input type="hidden" name="transaction_date" value="<?= View::e(date('Y-m-d')) ?>">
            <input type="hidden" name="redirect_to" value="<?= View::e($_SERVER['REQUEST_URI'] ?? '/') ?>">

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
                    <input type="text" id="quick-add-payee" name="payee" required class="quickadd-input">
                </div>
            </div>

            <div class="quickadd-field">
                <label for="quick-add-account" class="quickadd-field-label">Account</label>
                <div class="quickadd-input-shell">
                    <svg class="quickadd-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                    <select id="quick-add-account" name="account_id" required class="quickadd-select" data-swatch-target="quick-add-account-swatch">
                        <option value="">Select an account&hellip;</option>
                        <?php foreach ($quickAddAccounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>" data-color="<?= View::e($account['color'] ?: '#a8a29e') ?>"><?= View::e($account['name']) ?></option>
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
                        <?php foreach ($quickAddCategories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" data-color="<?= View::e($category['color'] ?: '#a8a29e') ?>"><?= View::e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="quick-add-category-swatch" class="quickadd-swatch" aria-hidden="true"></span>
                </div>
            </div>

            <button type="submit" class="quickadd-cta">Add transaction</button>
        </form>

        <p class="quickadd-footnote">Need to split it, add notes, or log income? Use the <a href="/transactions/create">full form</a> instead.</p>
    </div>
</div>

<script src="<?= View::asset('/assets/js/quick-add.js') ?>" defer></script>
<script src="<?= View::asset('/assets/js/modals.js') ?>" defer></script>
<script src="<?= View::asset('/assets/js/sidebar.js') ?>" defer></script>
