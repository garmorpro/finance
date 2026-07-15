<?php

/** @var string $periodMonth */
/** @var string $monthLabel */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var list<array{group: array|null, rows: list<array>}> $expenseSections */
/** @var list<array{group: array|null, rows: list<array>}> $incomeSections */
/** @var string $totalPlannedExpense */
/** @var string $totalActualExpense */
/** @var string $totalPlannedIncome */
/** @var string $totalActualIncome */
/** @var string $plannedSurplus */
/** @var string $actualSurplus */
/** @var string $plannedGoalContributions */
/** @var string $actualGoalContributions */
/** @var string $leftToBudget */
/** @var bool $canManage */
/** @var bool $hasPreviousBudget */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

// Display-only percentage for the progress bar — never used for a stored
// or reported financial figure, so bcmath here is about consistency, not
// correctness-under-rounding the way it is for real money math.
$percentFor = function (string $actual, string $planned): int {
    if (bccomp($planned, '0.00', 2) <= 0) {
        return bccomp($actual, '0.00', 2) > 0 ? 100 : 0;
    }
    $percent = (int) bcdiv(bcmul($actual, '100', 2), $planned, 0);
    return min(100, max(0, $percent));
};

$rowGrid = 'grid grid-cols-[minmax(0,1fr)_130px_100px_120px] items-center gap-3';

$renderRow = function (array $row, string $type) use ($csrfToken, $periodMonth, $canManage, $rowGrid, $percentFor): void {
    $category = $row['category'];
    $categoryId = (int) $category['id'];
    $planned = $row['planned'];
    $actual = $row['actual'];
    $remaining = $row['remaining'];
    $isExpense = $type === 'expense';
    $overBudget = $isExpense && $remaining !== null && bccomp($remaining, '0.00', 2) < 0;
    ?>
    <div class="<?= $rowGrid ?> py-3 border-b border-stone-100 dark:border-stone-800 last:border-b-0">
        <div class="flex items-center gap-2 min-w-0">
            <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background-color: <?= View::e($category['color'] ?? '#a8a29e') ?>"></span>
            <span class="text-sm text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></span>
        </div>
        <?php if ($canManage && $isExpense): ?>
            <form method="POST" action="/budgets/items" class="flex items-center gap-1.5 justify-self-end">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                <input type="text" inputmode="decimal" name="planned_amount" placeholder="0.00" value="<?= $planned !== null ? View::e($planned) : '' ?>" class="field-input w-20 text-right text-sm py-1.5 px-2">
                <button type="submit" class="text-xs font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Save</button>
            </form>
        <?php else: ?>
            <span class="text-sm text-stone-500 dark:text-stone-400 text-right"><?= $planned !== null ? Money::format($planned) : '—' ?></span>
        <?php endif; ?>
        <span class="text-sm text-stone-500 dark:text-stone-400 text-right"><?= Money::format($actual) ?></span>
        <span class="text-sm font-medium text-right <?= $overBudget ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>">
            <?php if ($planned === null): ?>
                &mdash;
            <?php elseif ($isExpense): ?>
                <?= $overBudget ? '-' . Money::format(bcmul($remaining, '-1', 2)) : Money::format($remaining) ?>
            <?php else: ?>
                <?= Money::format($remaining) ?>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($planned !== null): ?>
        <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-1 -mt-2 mb-1">
            <div class="h-1 rounded-full <?= $isExpense ? ($overBudget ? 'bg-red-500' : 'bg-terracotta-600') : 'bg-emerald-600' ?>" style="width: <?= $percentFor($actual, $planned) ?>%"></div>
        </div>
    <?php endif; ?>
    <?php
};

$renderSection = function (array $section, string $type) use ($renderRow, $rowGrid): void {
    $title = $section['group'] !== null ? $section['group']['name'] : 'Ungrouped';

    $planned = '0.00';
    $actual = '0.00';
    foreach ($section['rows'] as $row) {
        if ($row['planned'] !== null) {
            $planned = bcadd($planned, $row['planned'], 2);
            $actual = bcadd($actual, $row['actual'], 2);
        }
    }
    $remaining = bcsub($planned, $actual, 2);
    ?>
    <details class="card p-0 overflow-hidden" open>
        <summary class="<?= $rowGrid ?> px-6 py-4 cursor-pointer select-none">
            <span class="font-semibold text-stone-900 dark:text-white truncate"><?= View::e($title) ?></span>
            <span class="text-sm font-medium text-stone-500 dark:text-stone-400 text-right"><?= Money::format($planned) ?></span>
            <span class="text-sm font-medium text-stone-500 dark:text-stone-400 text-right"><?= Money::format($actual) ?></span>
            <?php if ($type === 'expense'): ?>
                <span class="text-sm font-semibold text-right <?= bccomp($remaining, '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($remaining) ?></span>
            <?php else: ?>
                <span class="text-sm font-semibold text-stone-500 dark:text-stone-400 text-right">&mdash;</span>
            <?php endif; ?>
        </summary>
        <div class="px-6 pb-4 border-t border-stone-100 dark:border-stone-800">
            <?php if ($section['rows'] === []): ?>
                <p class="text-sm text-stone-500 dark:text-stone-400 py-4">No categories in this section yet.</p>
            <?php else: ?>
                <?php foreach ($section['rows'] as $row): ?>
                    <?php $renderRow($row, $type); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>
    <?php
};

$summaryBar = function (string $label, string $planned, string $actual, string $remaining, string $earnedOrSpentLabel, string $barColorClass) use ($percentFor): void {
    ?>
    <div>
        <div class="flex items-baseline justify-between mb-1.5">
            <span class="text-sm font-medium text-stone-900 dark:text-white"><?= View::e($label) ?></span>
            <span class="text-xs text-stone-500 dark:text-stone-400"><?= Money::format($planned) ?> planned</span>
        </div>
        <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-1.5 mb-1.5">
            <div class="h-1.5 rounded-full <?= $barColorClass ?>" style="width: <?= $percentFor($actual, $planned) ?>%"></div>
        </div>
        <div class="flex items-baseline justify-between text-xs">
            <span class="text-stone-500 dark:text-stone-400"><?= Money::format($actual) ?> <?= View::e($earnedOrSpentLabel) ?></span>
            <span class="font-medium <?= bccomp($remaining, '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' ?>"><?= Money::format($remaining) ?> remaining</span>
        </div>
    </div>
    <?php
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'budgets']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Budgets</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                            Organized into sections you manage in
                            <a href="/settings/categories" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">Settings &rarr; Categories</a>.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/budgets?month=<?= View::e($prevMonth) ?>" class="btn-secondary" aria-label="Previous month">&larr;</a>
                        <span class="text-sm font-medium text-stone-900 dark:text-white w-32 text-center"><?= View::e($monthLabel) ?></span>
                        <a href="/budgets?month=<?= View::e($nextMonth) ?>" class="btn-secondary" aria-label="Next month">&rarr;</a>
                        <?php if ($canManage && $hasPreviousBudget): ?>
                            <form method="POST" action="/budgets/copy-previous">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                                <button type="submit" class="btn-secondary">Copy last month</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <div class="budget-layout">
                    <div class="space-y-6 min-w-0">
                        <div>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white mb-3">Income</h2>
                            <?php if ($incomeSections === []): ?>
                                <div class="card text-center py-10">
                                    <p class="text-stone-500 dark:text-stone-400 mb-2">No income categories yet.</p>
                                    <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($incomeSections as $section): ?>
                                        <?php $renderSection($section, 'income'); ?>
                                    <?php endforeach; ?>
                                    <div class="<?= $rowGrid ?> px-6 py-3">
                                        <span class="font-semibold text-stone-900 dark:text-white">Total Income</span>
                                        <span class="text-sm font-semibold text-stone-900 dark:text-white text-right"><?= Money::format($totalPlannedIncome) ?></span>
                                        <span class="text-sm font-semibold text-stone-900 dark:text-white text-right"><?= Money::format($totalActualIncome) ?></span>
                                        <span class="text-sm font-semibold text-stone-500 dark:text-stone-400 text-right">&mdash;</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h2 class="text-lg font-semibold text-stone-900 dark:text-white mb-3">Expenses</h2>
                            <?php if ($expenseSections === []): ?>
                                <div class="card text-center py-10">
                                    <p class="text-stone-500 dark:text-stone-400 mb-2">No expense categories yet.</p>
                                    <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($expenseSections as $section): ?>
                                        <?php $renderSection($section, 'expense'); ?>
                                    <?php endforeach; ?>
                                    <div class="<?= $rowGrid ?> px-6 py-3">
                                        <span class="font-semibold text-stone-900 dark:text-white">Total Expenses</span>
                                        <span class="text-sm font-semibold text-stone-900 dark:text-white text-right"><?= Money::format($totalPlannedExpense) ?></span>
                                        <span class="text-sm font-semibold text-stone-900 dark:text-white text-right"><?= Money::format($totalActualExpense) ?></span>
                                        <span class="text-sm font-semibold text-right <?= bccomp(bcsub($totalPlannedExpense, $totalActualExpense, 2), '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format(bcsub($totalPlannedExpense, $totalActualExpense, 2)) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="budget-sidebar space-y-4">
                        <div class="rounded-2xl p-6 text-center <?= bccomp($leftToBudget, '0.00', 2) < 0 ? 'bg-red-50 dark:bg-red-950/40' : 'bg-emerald-50 dark:bg-emerald-950/40' ?>">
                            <div class="text-3xl font-semibold <?= bccomp($leftToBudget, '0.00', 2) < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' ?>"><?= Money::format($leftToBudget) ?></div>
                            <div class="text-sm font-medium mt-1 <?= bccomp($leftToBudget, '0.00', 2) < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' ?>">Left to budget</div>
                        </div>

                        <div class="card space-y-5">
                            <div>
                                <div class="flex items-baseline justify-between mb-1.5">
                                    <span class="text-sm font-medium text-stone-900 dark:text-white">Income</span>
                                    <span class="text-xs text-stone-500 dark:text-stone-400">actual, this month</span>
                                </div>
                                <div class="text-lg font-semibold text-emerald-700 dark:text-emerald-400"><?= Money::format($totalActualIncome) ?></div>
                            </div>
                            <?php $summaryBar('Expenses', $totalPlannedExpense, $totalActualExpense, bcsub($totalPlannedExpense, $totalActualExpense, 2), 'spent', 'bg-terracotta-600'); ?>
                            <?php $summaryBar('Goals', $plannedGoalContributions, $actualGoalContributions, bcsub($plannedGoalContributions, $actualGoalContributions, 2), 'contributed', 'bg-terracotta-400'); ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
