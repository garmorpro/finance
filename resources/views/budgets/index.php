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
/** @var list<array{label: string, value: string}> $leftToBudgetTrend */
/** @var bool $canManage */
/** @var bool $hasPreviousBudget */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\CategoryGroupIcons;
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

// Expenses: exceeding the plan is the bad direction.
$expenseDelta = function (string $remaining): array {
    if (bccomp($remaining, '0.00', 2) < 0) {
        return ['class' => 'delta-chip-over', 'text' => Money::format(bcmul($remaining, '-1', 2)) . ' over'];
    }
    return ['class' => 'delta-chip-ok', 'text' => Money::format($remaining) . ' left'];
};

// Income/Goals: exceeding the plan is good news, not overage — earning or
// contributing more than planned shouldn't read as a warning the way
// overspending does.
$positiveFramingDelta = function (string $remaining, string $shortOfPlanVerb): array {
    if (bccomp($remaining, '0.00', 2) <= 0) {
        return ['class' => 'delta-chip-ok', 'text' => Money::format(bcmul($remaining, '-1', 2)) . ' above plan'];
    }
    return ['class' => 'delta-chip-neutral', 'text' => Money::format($remaining) . ' ' . $shortOfPlanVerb];
};

// Normalizes the trend series into an SVG polyline `points` string for the
// hero's sparkline — pure display math, not a financial calculation.
$sparklinePoints = function (array $trend): string {
    $values = array_map(fn (array $t): float => (float) $t['value'], $trend);
    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $width = 150;
    $height = 40;
    $steps = count($values) - 1;

    $points = [];
    foreach ($values as $i => $value) {
        $x = $steps > 0 ? ($i / $steps) * $width : 0;
        $y = $range > 0 ? $height - (($value - $min) / $range) * $height : $height / 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    return implode(' ', $points);
};

$renderRow = function (array $row, string $type) use ($csrfToken, $periodMonth, $canManage, $percentFor): void {
    $category = $row['category'];
    $categoryId = (int) $category['id'];
    $planned = $row['planned'];
    $actual = $row['actual'];
    ?>
    <div class="budget-cat-row">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($category['color'] ?? '#a8a29e') ?>"></span>
                <span class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></span>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="text-sm text-stone-500 dark:text-stone-400"><?= Money::format($actual) ?></span>
                <span class="text-stone-300 dark:text-stone-600">/</span>
                <?php if ($canManage): ?>
                    <form method="POST" action="/budgets/items" class="budget-item-form relative" data-autosave="budget-item">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <span class="budget-item-status absolute right-0 -top-5 text-xs text-stone-500 dark:text-stone-400" aria-live="polite"></span>
                        <input type="text" inputmode="decimal" name="planned_amount" placeholder="0.00" value="<?= $planned !== null ? View::e($planned) : '' ?>" class="budget-item-input budget-cat-input" aria-label="Planned amount for <?= View::e($category['name']) ?>">

                        <div class="budget-item-history budget-history-panel hidden" style="position: fixed; width: 19rem; z-index: 30;" role="dialog" aria-label="Spending history for <?= View::e($category['name']) ?>">
                            <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-0.5">Last month</div>
                                    <div class="budget-item-history-last text-sm font-semibold text-stone-900 dark:text-white">&mdash;</div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-0.5">6-mo average</div>
                                    <div class="budget-item-history-avg text-sm font-semibold text-stone-900 dark:text-white">&mdash;</div>
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-xs text-stone-600 dark:text-stone-300 cursor-pointer">
                                <input type="checkbox" name="apply_to_future" value="1" class="budget-item-apply-future">
                                <span class="budget-item-apply-future-label">Apply to all future months</span>
                            </label>
                        </div>
                    </form>
                <?php else: ?>
                    <span class="font-semibold text-stone-900 dark:text-white inline-block text-right w-[6.5rem]"><?= $planned !== null ? Money::format($planned) : '—' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($planned !== null): ?>
            <div class="budget-cat-bar-track">
                <?php
                $isExpense = $type === 'expense';
                $overBudget = $isExpense && bccomp(bcsub($planned, $actual, 2), '0.00', 2) < 0;
                $barColors = $overBudget ? ['#dc2626', '#f87171'] : ($isExpense ? ['#c94f32', '#e2694b'] : ['#059669', '#34d399']);
                ?>
                <div class="budget-cat-bar-fill" style="width: <?= $percentFor($actual, $planned) ?>%; --bar-a: <?= $barColors[0] ?>; --bar-b: <?= $barColors[1] ?>;"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
};

$renderSection = function (array $section, string $type) use ($renderRow, $expenseDelta, $positiveFramingDelta): void {
    $group = $section['group'];
    $title = $group !== null ? $group['name'] : 'Ungrouped';

    $planned = '0.00';
    $actual = '0.00';
    foreach ($section['rows'] as $row) {
        if ($row['planned'] !== null) {
            $planned = bcadd($planned, $row['planned'], 2);
            $actual = bcadd($actual, $row['actual'], 2);
        }
    }
    $remaining = bcsub($planned, $actual, 2);
    $delta = $type === 'expense' ? $expenseDelta($remaining) : $positiveFramingDelta($remaining, 'left to earn');
    ?>
    <details class="card budget-section-card p-0" open>
        <summary class="flex items-center justify-between gap-4 px-6 py-4 cursor-pointer select-none">
            <span class="flex items-center gap-3 min-w-0">
                <span class="budget-group-icon"><?= CategoryGroupIcons::forName($group !== null ? $group['name'] : null) ?></span>
                <span class="font-semibold text-stone-900 dark:text-white truncate"><?= View::e($title) ?></span>
            </span>
            <span class="flex items-center gap-3 text-sm flex-shrink-0">
                <span class="text-stone-500 dark:text-stone-400"><?= Money::format($actual) ?> <span class="text-stone-400 dark:text-stone-600">of</span> <?= Money::format($planned) ?></span>
                <span class="delta-chip <?= $delta['class'] ?>"><?= View::e($delta['text']) ?></span>
            </span>
        </summary>
        <div class="px-4 pb-2 border-t border-stone-100 dark:border-stone-800 pt-2">
            <?php if ($section['rows'] === []): ?>
                <p class="text-sm text-stone-500 dark:text-stone-400 py-4 px-2">No categories in this section yet.</p>
            <?php else: ?>
                <?php foreach ($section['rows'] as $row): ?>
                    <?php $renderRow($row, $type); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>
    <?php
};

$statCard = function (string $label, string $planned, string $actual, array $delta, string $earnedOrSpentLabel, string $iconBg, string $iconColor, string $iconSvg, array $barColors) use ($percentFor): void {
    ?>
    <div class="card">
        <div class="flex items-center gap-3 mb-3">
            <span class="metric-icon" style="background: <?= $iconBg ?>; color: <?= $iconColor ?>;"><?= $iconSvg ?></span>
            <div>
                <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white"><?= View::e($label) ?></div>
                <div class="text-xs text-stone-500 dark:text-stone-400"><?= Money::format($planned) ?> planned</div>
            </div>
        </div>
        <div class="budget-cat-bar-track">
            <div class="budget-cat-bar-fill" style="width: <?= $percentFor($actual, $planned) ?>%; --bar-a: <?= $barColors[0] ?>; --bar-b: <?= $barColors[1] ?>;"></div>
        </div>
        <div class="flex items-center justify-between mt-3 text-xs">
            <span class="text-stone-500 dark:text-stone-400"><?= Money::format($actual) ?> <?= View::e($earnedOrSpentLabel) ?></span>
            <span class="delta-chip <?= $delta['class'] ?>"><?= View::e($delta['text']) ?></span>
        </div>
    </div>
    <?php
};

$arrowUpIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
$targetIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>';

$leftToBudgetPositive = bccomp($leftToBudget, '0.00', 2) >= 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Budgets · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'budgets']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between flex-wrap gap-3">
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
                        <?php if ($canManage): ?>
                            <a href="/budgets/review?month=<?= View::e(substr($periodMonth, 0, 7)) ?>" class="btn-primary">Review &amp; plan &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <!-- Left to Budget hero -->
                <div class="stat-hero <?= $leftToBudgetPositive ? 'stat-hero-positive' : 'stat-hero-negative' ?> flex items-center justify-between flex-wrap gap-6">
                    <span class="stat-hero-icon <?= $leftToBudgetPositive ? 'text-emerald-600' : 'text-red-600' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div style="position: relative;">
                        <div class="text-xs font-bold uppercase tracking-wide <?= $leftToBudgetPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?> mb-2">Left to Budget &middot; <?= View::e(date('F', strtotime($periodMonth))) ?></div>
                        <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($leftToBudget) ?></div>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Planned income minus planned expenses and goal contributions.</p>
                    </div>
                    <div style="position: relative;">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400 mb-2">Last 6 months</div>
                        <svg width="150" height="46" viewBox="0 0 150 46" role="img" aria-label="Left to budget over the last 6 months">
                            <polyline points="<?= $sparklinePoints($leftToBudgetTrend) ?>" fill="none" stroke="<?= $leftToBudgetPositive ? '#34d399' : '#f87171' ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                <!-- Summary row: Income / Expenses / Goals -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php
                    $statCard(
                        'Income',
                        $totalPlannedIncome,
                        $totalActualIncome,
                        $positiveFramingDelta(bcsub($totalPlannedIncome, $totalActualIncome, 2), 'left to earn'),
                        'earned',
                        'rgba(52, 211, 153, .14)',
                        '#059669',
                        $arrowUpIcon,
                        ['#059669', '#34d399']
                    );
                    ?>
                    <?php
                    $statCard(
                        'Expenses',
                        $totalPlannedExpense,
                        $totalActualExpense,
                        $expenseDelta(bcsub($totalPlannedExpense, $totalActualExpense, 2)),
                        'spent',
                        'rgba(226, 105, 75, .14)',
                        '#c94f32',
                        $arrowDownIcon,
                        ['#c94f32', '#e2694b']
                    );
                    ?>
                    <?php
                    $statCard(
                        'Goals',
                        $plannedGoalContributions,
                        $actualGoalContributions,
                        $positiveFramingDelta(bcsub($plannedGoalContributions, $actualGoalContributions, 2), 'left to contribute'),
                        'contributed',
                        'rgba(168, 162, 158, .16)',
                        '#78716c',
                        $targetIcon,
                        ['#78716c', '#a8a29e']
                    );
                    ?>
                </div>

                <!-- Income -->
                <div>
                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white mb-3">Income</h2>
                    <?php if ($incomeSections === []): ?>
                        <div class="card text-center py-10">
                            <p class="text-stone-500 dark:text-stone-400 mb-2">No income categories yet.</p>
                            <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($incomeSections as $section): ?>
                            <?php $renderSection($section, 'income'); ?>
                        <?php endforeach; ?>
                        <div class="budget-total-row flex items-center justify-between">
                            <span class="font-semibold text-stone-900 dark:text-white">Total Income</span>
                            <span class="text-sm text-stone-600 dark:text-stone-300"><span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualIncome) ?></span> of <?= Money::format($totalPlannedIncome) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Expenses -->
                <div>
                    <h2 class="text-lg font-semibold text-stone-900 dark:text-white mb-3">Expenses</h2>
                    <?php if ($expenseSections === []): ?>
                        <div class="card text-center py-10">
                            <p class="text-stone-500 dark:text-stone-400 mb-2">No expense categories yet.</p>
                            <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($expenseSections as $section): ?>
                            <?php $renderSection($section, 'expense'); ?>
                        <?php endforeach; ?>
                        <div class="budget-total-row flex items-center justify-between">
                            <span class="font-semibold text-stone-900 dark:text-white">Total Expenses</span>
                            <span class="text-sm text-stone-600 dark:text-stone-300"><span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualExpense) ?></span> of <?= Money::format($totalPlannedExpense) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= View::asset('/assets/js/budgets.js') ?>" defer></script>
</body>
</html>
