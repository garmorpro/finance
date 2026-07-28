<?php

/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

$hasActivity = $allocationSummary !== null && bccomp($allocationSummary['income'], '0.00', 2) > 0;
$isOverspent = $hasActivity && bccomp($allocationSummary['remaining'], '0.00', 2) < 0;

View::partial('dashboard/widgets/_header', ['title' => 'The Lifecycle of a Dollar', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<p class="text-xs text-stone-500 dark:text-stone-400 mb-3">Follow this month's income through to what's actually left over.</p>

<?php if (!$hasActivity): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No income recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <div style="display:flex; flex-direction:column;">
        <div class="lifecycle-item">
            <span style="display:flex; align-items:center; gap:0.6rem;">
                <span class="legend-dot" style="background:#78716c"></span>
                <span class="text-sm text-stone-900 dark:text-white">Household Income</span>
            </span>
            <span class="text-sm font-semibold text-stone-900 dark:text-white"><?= Money::format($allocationSummary['income']) ?></span>
        </div>
        <div class="lifecycle-arrow">&darr;</div>

        <div class="lifecycle-item">
            <span style="display:flex; align-items:center; gap:0.6rem;">
                <span class="legend-dot" style="background:#c94f32"></span>
                <span class="text-sm text-stone-900 dark:text-white">Living Expenses</span>
            </span>
            <span class="text-sm font-medium text-stone-900 dark:text-white">(<?= Money::format($allocationSummary['livingExpenses']) ?>)</span>
        </div>
        <div class="lifecycle-arrow">&darr;</div>

        <div class="lifecycle-item">
            <span style="display:flex; align-items:center; gap:0.6rem;">
                <span class="legend-dot" style="background:#a53d27"></span>
                <span class="text-sm text-stone-900 dark:text-white">Debt Payments</span>
            </span>
            <span class="text-sm font-medium text-stone-900 dark:text-white">(<?= Money::format($allocationSummary['debtPayments']) ?>)</span>
        </div>
        <div class="lifecycle-arrow">&darr;</div>

        <div class="lifecycle-item">
            <span style="display:flex; align-items:center; gap:0.6rem;">
                <span class="legend-dot" style="background:#059669"></span>
                <span class="text-sm text-stone-900 dark:text-white">Savings &amp; Investing</span>
            </span>
            <span class="text-sm font-medium text-stone-900 dark:text-white">(<?= Money::format($allocationSummary['savings']) ?>)</span>
        </div>
        <div class="lifecycle-arrow">&darr;</div>

        <div class="lifecycle-item">
            <span style="display:flex; align-items:center; gap:0.6rem;">
                <span class="legend-dot" style="background:#a8a29e"></span>
                <span class="text-sm text-stone-900 dark:text-white">Giving</span>
            </span>
            <span class="text-sm font-medium text-stone-900 dark:text-white">(<?= Money::format($allocationSummary['giving']) ?>)</span>
        </div>

        <div class="lifecycle-remaining <?= $isOverspent ? 'lifecycle-remaining-negative' : '' ?>">
            <span class="text-sm font-semibold <?= $isOverspent ? 'lifecycle-remaining-negative-text' : 'lifecycle-remaining-text' ?>">Remaining Cash</span>
            <span class="text-base font-semibold <?= $isOverspent ? 'lifecycle-remaining-negative-text' : 'lifecycle-remaining-text' ?>"><?= Money::format($allocationSummary['remaining']) ?></span>
        </div>
    </div>
<?php endif; ?>
