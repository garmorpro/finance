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

$renderRow = function (array $row, string $type) use ($csrfToken, $periodMonth, $canManage, $percentFor): void {
    $category = $row['category'];
    $categoryId = (int) $category['id'];
    $planned = $row['planned'];
    $actual = $row['actual'];
    $remaining = $row['remaining'];
    $isExpense = $type === 'expense';
    $overBudget = $isExpense && $remaining !== null && bccomp($remaining, '0.00', 2) < 0;
    ?>
    <div class="py-4 first:pt-0 last:pb-0">
        <div class="flex items-center justify-between gap-4 mb-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background-color: <?= View::e($category['color'] ?? '#a8a29e') ?>"></span>
                <span class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></span>
            </div>
            <div class="flex items-center gap-4 text-sm shrink-0">
                <span class="text-stone-500 dark:text-stone-400">
                    <?= Money::format($actual) ?><?= $planned !== null ? ' of ' . Money::format($planned) : ($isExpense ? ' spent' : ' received') ?>
                </span>
                <?php if ($planned !== null): ?>
                    <span class="font-medium w-32 text-right <?= $overBudget ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>">
                        <?php if ($isExpense): ?>
                            <?= $overBudget ? 'Over by ' . Money::format(bcmul($remaining, '-1', 2)) : Money::format($remaining) . ' left' ?>
                        <?php elseif (bccomp($remaining, '0.00', 2) > 0): ?>
                            <?= Money::format($remaining) ?> short
                        <?php elseif (bccomp($remaining, '0.00', 2) < 0): ?>
                            +<?= Money::format(bcmul($remaining, '-1', 2)) ?> extra
                        <?php else: ?>
                            Fully received
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <?php if ($canManage): ?>
                    <form method="POST" action="/budgets/items" class="flex items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="text" inputmode="decimal" name="planned_amount" placeholder="0.00" value="<?= $planned !== null ? View::e($planned) : '' ?>" class="field-input w-24 text-right">
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($planned !== null): ?>
            <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-1.5">
                <div class="h-1.5 rounded-full <?= $isExpense ? ($overBudget ? 'bg-red-500' : 'bg-terracotta-600') : 'bg-emerald-600' ?>" style="width: <?= $percentFor($actual, $planned) ?>%"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
};

$renderSection = function (array $section, string $type) use ($renderRow): void {
    $title = $section['group'] !== null ? $section['group']['name'] : 'Ungrouped';
    ?>
    <div class="card divide-y divide-stone-100 dark:divide-stone-800">
        <h3 class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wide pb-3"><?= View::e($title) ?></h3>
        <?php if ($section['rows'] === []): ?>
            <p class="text-sm text-stone-400 dark:text-stone-600 py-4">No categories in this section yet.</p>
        <?php else: ?>
            <?php foreach ($section['rows'] as $row): ?>
                <?php $renderRow($row, $type); ?>
            <?php endforeach; ?>
        <?php endif; ?>
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

                <div class="card">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <div class="label-text">Income</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualIncome) ?></div>
                            <div class="text-xs text-stone-500 dark:text-stone-400 mt-0.5"><?= Money::format($totalPlannedIncome) ?> planned</div>
                        </div>
                        <div>
                            <div class="label-text">Expenses</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualExpense) ?></div>
                            <div class="text-xs text-stone-500 dark:text-stone-400 mt-0.5"><?= Money::format($totalPlannedExpense) ?> planned</div>
                        </div>
                        <div>
                            <div class="label-text">Surplus</div>
                            <div class="text-xl font-semibold <?= bccomp($actualSurplus, '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= Money::format($actualSurplus) ?></div>
                            <div class="text-xs text-stone-500 dark:text-stone-400 mt-0.5"><?= Money::format($plannedSurplus) ?> planned</div>
                        </div>
                    </div>
                </div>

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
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
