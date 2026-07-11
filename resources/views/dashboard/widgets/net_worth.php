<?php

/** @var array|null $netWorth */

use App\Support\Money;

?>
<div class="tile-header">
    <span class="label-text mb-0">Net Worth</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($netWorth === null): ?>
    <div class="tile-placeholder"><p class="text-sm">No household found.</p></div>
<?php else: ?>
    <div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= Money::format($netWorth['net']) ?></div>
    <div class="flex items-center gap-4 mt-3 text-sm">
        <span class="text-emerald-600 dark:text-emerald-400">Assets <?= Money::format($netWorth['assets']) ?></span>
        <span class="text-red-600 dark:text-red-400">Debt <?= Money::format($netWorth['liabilities']) ?></span>
    </div>
<?php endif; ?>
