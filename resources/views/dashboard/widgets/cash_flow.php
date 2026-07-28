<?php

/** @var array{label: string, income: string, expenses: string, net: string, cumulative: string}|null $thisMonthCashFlow */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\Money;
use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Cash Flow This Month', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<?php if ($thisMonthCashFlow === null): ?>
    <div class="text-2xl font-semibold text-stone-900 dark:text-white">—</div>
<?php else: ?>
    <div class="text-2xl font-semibold text-stone-900 dark:text-white">
        <?= bccomp($thisMonthCashFlow['net'], '0.00', 2) >= 0 ? '+' : '' ?><?= Money::format($thisMonthCashFlow['net']) ?>
    </div>
    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1"><?= Money::format($thisMonthCashFlow['income']) ?> in &middot; <?= Money::format($thisMonthCashFlow['expenses']) ?> out</p>
<?php endif; ?>
