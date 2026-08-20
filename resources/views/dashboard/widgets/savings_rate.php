<?php

/** @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Services\ReportingService;
use App\Support\View;

$savingsRate = (new ReportingService())->savingsRate($allocationSummary);

View::partial('dashboard/widgets/_header', ['title' => 'Savings Rate', 'widgetKey' => $widgetKey, 'isWide' => $isWide, 'resizable' => false]);

?>
<div class="text-2xl font-semibold text-stone-900 dark:text-white"><?= $savingsRate === null ? '—' : number_format($savingsRate, 0) . '%' ?></div>
<p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Of income directed to savings &amp; investing</p>
