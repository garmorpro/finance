<?php

/**
 * "The Lifecycle of a Dollar" — a fixed panel rendered directly in the
 * main column of dashboard/index.php, not a draggable/hideable
 * registry tile (same convention the old lifecycle.php sidebar used).
 * Replaces both the old Capital Allocation donut and the old sticky
 * lifecycle sidebar with one horizontal waterfall: income steps down
 * through each spending category to what's actually left over, read
 * left to right.
 *
 * @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary
 */

use App\Support\Money;

$hasActivity = $allocationSummary !== null && bccomp($allocationSummary['income'], '0.00', 2) > 0;

if ($hasActivity) {
    $income = $allocationSummary['income'];
    $isOverspent = bccomp($allocationSummary['remaining'], '0.00', 2) < 0;

    $categories = [
        ['label' => 'Living Expenses', 'amount' => $allocationSummary['livingExpenses'], 'color' => '#c94f32'],
        ['label' => 'Debt Payments', 'amount' => $allocationSummary['debtPayments'], 'color' => '#a53d27'],
        ['label' => 'Savings & Investing', 'amount' => $allocationSummary['savings'], 'color' => '#059669'],
        ['label' => 'Giving', 'amount' => $allocationSummary['giving'], 'color' => '#a8a29e'],
    ];

    // Running total after each deduction, used to position each
    // floating bar. A category amount is always a non-negative
    // magnitude (monthlyAllocationSummary()'s contract), so this chain
    // only heads downward — it can still cross zero in an overspent
    // month, which toHeight() below floors to 0 rather than drawing a
    // bar with negative height. The Remaining Cash bar and its label
    // still show the real (possibly negative) figure; only the middle
    // bars simplify once the running total goes underwater.
    $running = $income;
    foreach ($categories as $i => $cat) {
        $running = bcsub($running, $cat['amount'], 2);
        $categories[$i]['runningAfter'] = $running;
    }

    $remainingAbs = $isOverspent ? bcmul($allocationSummary['remaining'], '-1', 2) : $allocationSummary['remaining'];
    $scale = bccomp($remainingAbs, $income, 2) > 0 ? $remainingAbs : $income;

    $maxHeightPx = 170.0;
    $toHeight = static function (string $amount) use ($scale, $maxHeightPx): float {
        if (bccomp($scale, '0.00', 2) <= 0) {
            return 0.0;
        }

        return max(0.0, (float) bcdiv(bcmul($amount, (string) $maxHeightPx, 4), $scale, 2));
    };
}

?>
<div class="flex items-baseline justify-between flex-wrap gap-1 mb-6">
    <h2 class="text-base font-semibold text-stone-900 dark:text-white">The Lifecycle of a Dollar</h2>
    <p class="text-xs text-stone-500 dark:text-stone-400">How this month's income turned into what's left over</p>
</div>

<?php if (!$hasActivity): ?>
    <div class="tile-placeholder">
        <p class="text-sm mb-2">No income recorded this month.</p>
        <a href="/transactions/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
    </div>
<?php else: ?>
    <?php $incomeHeight = $toHeight($income); ?>
    <div class="waterfall">
        <div class="wf-col">
            <span class="wf-amt tabular-nums"><?= Money::format($income) ?></span>
            <div class="wf-bar-wrap"><div class="wf-bar" style="bottom:0; height:<?= $incomeHeight ?>px; background:#a8a29e;"></div></div>
            <span class="wf-label"><span class="wf-dot" style="background:#a8a29e;"></span>Income</span>
        </div>

        <?php $top = $incomeHeight; ?>
        <?php foreach ($categories as $cat): ?>
            <span class="wf-arrow">&rarr;</span>
            <?php
            $bottom = $toHeight($cat['runningAfter']);
            $height = max(2.0, $top - $bottom);
            ?>
            <div class="wf-col">
                <span class="wf-amt tabular-nums neg">&minus;<?= Money::format($cat['amount']) ?></span>
                <div class="wf-bar-wrap"><div class="wf-bar" style="bottom:<?= $bottom ?>px; height:<?= $height ?>px; background:<?= $cat['color'] ?>;"></div></div>
                <span class="wf-label"><span class="wf-dot" style="background:<?= $cat['color'] ?>;"></span><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></span>
            </div>
            <?php $top = $bottom; ?>
        <?php endforeach; ?>

        <span class="wf-arrow">&rarr;</span>
        <div class="wf-col final <?= $isOverspent ? 'overspent' : '' ?>">
            <span class="wf-amt tabular-nums"><?= Money::format($allocationSummary['remaining']) ?></span>
            <div class="wf-bar-wrap"><div class="wf-bar" style="bottom:0; height:<?= $toHeight($remainingAbs) ?>px; background:<?= $isOverspent ? '#dc2626' : '#15803d' ?>;"></div></div>
            <span class="wf-label"><span class="wf-dot" style="background:<?= $isOverspent ? '#dc2626' : '#15803d' ?>;"></span>Remaining Cash</span>
        </div>
    </div>
<?php endif; ?>
