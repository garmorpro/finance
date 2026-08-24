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

// "$X of $Y planned" only reads sensibly once something's actually been
// planned — with nothing planned it degrades to "$X of $0.00", which
// reads like a fraction of nothing rather than a status. Below that
// threshold we say what happened in plain words instead.
$actualOfPlannedLabel = function (string $actual, string $planned, string $verb): string {
    if (bccomp($planned, '0.00', 2) > 0) {
        return Money::format($actual) . ' <span class="text-stone-400 dark:text-stone-600">of</span> ' . Money::format($planned);
    }

    return bccomp($actual, '0.00', 2) > 0
        ? Money::format($actual) . ' ' . $verb . ' <span class="text-stone-400 dark:text-stone-600">&middot; no budget set</span>'
        : '';
};

// Normalizes the trend series into an SVG chart path for the hero's
// area-filled sparkline — pure display math, not a financial calculation.
// Matches dashboard/widgets/hero_stats.php's exact chart conventions
// (400x120 viewBox, same gridlines/gradient/endpoint treatment) so the
// two heroes read as the same component.
$chartPath = function (array $trend): string {
    $values = array_map(fn (array $t): float => (float) $t['value'], $trend);
    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $count = count($values);

    $points = [];
    foreach ($values as $i => $value) {
        $x = $count > 1 ? ($i / ($count - 1)) * 400 : 200;
        $y = $range > 0 ? 110 - (($value - $min) / $range) * 100 : 60;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    return implode(' L', $points);
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

$renderSection = function (array $section, string $type) use ($renderRow, $expenseDelta, $positiveFramingDelta, $actualOfPlannedLabel): void {
    $group = $section['group'];
    $title = $group !== null ? $group['name'] : 'Ungrouped';
    $rows = $section['rows'];

    $planned = '0.00';
    $actual = '0.00';
    foreach ($rows as $row) {
        if ($row['planned'] !== null) {
            $planned = bcadd($planned, $row['planned'], 2);
            $actual = bcadd($actual, $row['actual'], 2);
        }
    }
    $remaining = bcsub($planned, $actual, 2);
    $hasActivity = bccomp($planned, '0.00', 2) > 0 || bccomp($actual, '0.00', 2) > 0;

    if ($hasActivity) {
        $delta = $type === 'expense' ? $expenseDelta($remaining) : $positiveFramingDelta($remaining, 'left to earn');
        $summaryLabel = $actualOfPlannedLabel($actual, $planned, $type === 'expense' ? 'spent' : 'earned');
    } else {
        $delta = ['class' => 'delta-chip-neutral', 'text' => 'No activity'];
        $summaryLabel = '';
    }

    $count = count($rows);

    // Rows with neither a plan nor any spending are folded into a
    // "N more categories..." disclosure instead of each getting a full
    // row — this only ever fires inside a group that's open (i.e. has
    // at least one row with real activity), since a group with zero
    // activity anywhere in it doesn't default-open at all.
    $activeRows = array_values(array_filter($rows, fn (array $r): bool => $r['planned'] !== null || bccomp($r['actual'], '0.00', 2) > 0));
    $emptyRows = array_values(array_filter($rows, fn (array $r): bool => $r['planned'] === null && bccomp($r['actual'], '0.00', 2) === 0));
    ?>
    <details class="card budget-section-card p-0" <?= $hasActivity ? 'open' : '' ?>>
        <summary class="flex items-center justify-between gap-4 px-6 py-4 cursor-pointer select-none">
            <span class="flex items-center gap-3 min-w-0">
                <span class="budget-group-icon"><?= CategoryGroupIcons::forName($group !== null ? $group['name'] : null) ?></span>
                <span class="font-semibold text-stone-900 dark:text-white truncate"><?= View::e($title) ?></span>
                <?php if ($count > 0): ?>
                    <span class="delta-chip delta-chip-neutral"><?= $count ?> categor<?= $count === 1 ? 'y' : 'ies' ?></span>
                <?php endif; ?>
            </span>
            <span class="flex items-center gap-3 text-sm flex-shrink-0">
                <?php if ($summaryLabel !== ''): ?>
                    <span class="text-stone-500 dark:text-stone-400"><?= $summaryLabel ?></span>
                <?php endif; ?>
                <span class="delta-chip <?= $delta['class'] ?>"><?= View::e($delta['text']) ?></span>
            </span>
        </summary>
        <div class="px-4 pb-2 border-t border-stone-100 dark:border-stone-800 pt-2">
            <?php if ($rows === []): ?>
                <p class="text-sm text-stone-500 dark:text-stone-400 py-4 px-2">No categories in this section yet.</p>
            <?php else: ?>
                <?php foreach ($activeRows as $row): ?>
                    <?php $renderRow($row, $type); ?>
                <?php endforeach; ?>
                <?php if ($emptyRows !== []): ?>
                    <?php $emptyCount = count($emptyRows); ?>
                    <details class="mt-1">
                        <summary class="text-xs text-stone-500 dark:text-stone-400 px-2 py-2 cursor-pointer select-none">
                            <?= $emptyCount ?> more categor<?= $emptyCount === 1 ? 'y' : 'ies' ?> with no plan or activity yet
                        </summary>
                        <?php foreach ($emptyRows as $row): ?>
                            <?php $renderRow($row, $type); ?>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </details>
    <?php
};

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
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <!-- Unified dark hero, matching Overview/Transactions/Accounts -->
                <div class="dash-hero">
                    <div class="dash-hero-top">
                        <div>
                            <h1>Budgets</h1>
                            <p class="sub">Organized into sections you manage in <a href="/settings/categories" style="color:#f0a488;">Settings &rarr; Categories</a>.</p>
                        </div>
                        <div class="dash-hero-actions">
                            <a href="/budgets?month=<?= View::e($prevMonth) ?>" class="btn-ghost-dark" aria-label="Previous month">&larr;</a>
                            <span class="text-sm font-medium text-white w-28 text-center"><?= View::e($monthLabel) ?></span>
                            <a href="/budgets?month=<?= View::e($nextMonth) ?>" class="btn-ghost-dark" aria-label="Next month">&rarr;</a>
                            <?php if ($canManage && $hasPreviousBudget): ?>
                                <form method="POST" action="/budgets/copy-previous">
                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                                    <button type="submit" class="btn-ghost-dark">Copy last month</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <a href="/budgets/review?month=<?= View::e(substr($periodMonth, 0, 7)) ?>" class="btn-primary">Review &amp; plan &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dash-hero-body">
                        <div class="dash-hero-nw">
                            <p class="dash-hero-eyebrow">Left to Budget</p>
                            <div class="dash-hero-nw-value tabular-nums"><?= Money::format($leftToBudget) ?></div>
                            <p class="dash-hero-nw-caption" style="margin-top:0.75rem;">Planned income minus planned expenses and goal contributions.</p>

                            <svg class="dash-hero-chart" viewBox="0 0 400 120" preserveAspectRatio="none" role="img" aria-label="Left to budget over the past <?= count($leftToBudgetTrend) ?> months">
                                <defs>
                                    <linearGradient id="budgetHeroFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#e2694b" stop-opacity="0.38"/>
                                        <stop offset="100%" stop-color="#e2694b" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <line x1="0" y1="30" x2="400" y2="30" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                <line x1="0" y1="70" x2="400" y2="70" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                <line x1="0" y1="110" x2="400" y2="110" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                                <?php $path = $chartPath($leftToBudgetTrend); ?>
                                <path d="M<?= $path ?> L400,120 L0,120 Z" fill="url(#budgetHeroFill)"/>
                                <path d="M<?= $path ?>" fill="none" stroke="#f0a488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <?php $lastPoint = explode(',', substr($path, strrpos($path, 'L') + 1)); ?>
                                <circle cx="<?= $lastPoint[0] ?>" cy="<?= $lastPoint[1] ?>" r="4.5" fill="#1c1917" stroke="#f0a488" stroke-width="2"/>
                            </svg>
                        </div>

                        <div class="dash-hero-stats">
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon income">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                                </span>
                                <span>
                                    <span class="dash-hero-stat-label">Income &middot; <?= Money::format($totalPlannedIncome) ?> planned</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= Money::format($totalActualIncome) ?></span>
                                </span>
                            </div>
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon expense">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                                </span>
                                <span>
                                    <span class="dash-hero-stat-label">Expenses &middot; <?= Money::format($totalPlannedExpense) ?> planned</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= Money::format($totalActualExpense) ?></span>
                                </span>
                            </div>
                            <div class="dash-hero-stat">
                                <span class="dash-hero-stat-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
                                </span>
                                <span>
                                    <span class="dash-hero-stat-label">Goals &middot; <?= Money::format($plannedGoalContributions) ?> planned</span>
                                    <span class="dash-hero-stat-value tabular-nums"><?= Money::format($actualGoalContributions) ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
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
                            <span class="text-sm text-stone-600 dark:text-stone-300">
                                <?php if (bccomp($totalPlannedIncome, '0.00', 2) > 0): ?>
                                    <span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualIncome) ?></span> of <?= Money::format($totalPlannedIncome) ?>
                                <?php elseif (bccomp($totalActualIncome, '0.00', 2) > 0): ?>
                                    <span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualIncome) ?></span> earned &middot; no budget set
                                <?php else: ?>
                                    No budget set yet
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Expenses -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Expenses</h2>
                        <?php if ($expenseSections !== []): ?>
                            <button type="button" id="budget-expand-all" class="text-xs font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Expand all</button>
                        <?php endif; ?>
                    </div>
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
                            <span class="text-sm text-stone-600 dark:text-stone-300">
                                <?php
                                $expenseRemaining = bcsub($totalPlannedExpense, $totalActualExpense, 2);
                                ?>
                                <?php if (bccomp($totalPlannedExpense, '0.00', 2) > 0): ?>
                                    <span class="font-semibold <?= bccomp($expenseRemaining, '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($totalActualExpense) ?></span> of <?= Money::format($totalPlannedExpense) ?>
                                <?php elseif (bccomp($totalActualExpense, '0.00', 2) > 0): ?>
                                    <span class="font-semibold text-stone-900 dark:text-white"><?= Money::format($totalActualExpense) ?></span> spent &middot; no budget set
                                <?php else: ?>
                                    No budget set yet
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= View::asset('/assets/js/budgets.js') ?>" defer></script>
</body>
</html>
