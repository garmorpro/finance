<?php

/** @var float|null $runwayMonths */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\View;

View::partial('dashboard/widgets/_header', ['title' => 'Runway', 'widgetKey' => $widgetKey, 'isWide' => $isWide]);

?>
<div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $runwayMonths === null ? '—' : number_format($runwayMonths, 1) . ' months' ?></div>
<p class="text-xs text-stone-500 dark:text-stone-400 mt-1">How long savings cover your baseline spending</p>
