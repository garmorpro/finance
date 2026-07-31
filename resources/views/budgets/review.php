<?php

/** @var string $periodMonth */
/** @var string $monthLabel */
/** @var list<array{group: array|null, rows: list<array>}> $incomeSteps */
/** @var list<array{group: array|null, rows: list<array>}> $expenseSteps */
/** @var string $plannedGoalContributions */
/** @var bool $hasPreviousBudget */
/** @var string $csrfToken */
/** @var string|null $notice */
/** @var string|null $error */

use App\Support\CategoryGroupIcons;
use App\Support\Money;
use App\Support\View;

$monthQuery = substr($periodMonth, 0, 7);

// One step per category group that actually has categories in it — income
// groups first, then expense groups — plus a final synthetic Summary step.
$steps = [];
foreach ($incomeSteps as $section) {
    $steps[] = ['type' => 'income', 'section' => $section];
}
foreach ($expenseSteps as $section) {
    $steps[] = ['type' => 'expense', 'section' => $section];
}

$stepLabel = function (array $step): string {
    $groupName = $step['section']['group']['name'] ?? null;
    if ($groupName !== null) {
        return $groupName;
    }
    return $step['type'] === 'income' ? 'Income' : 'Ungrouped';
};

$stepIcon = function (array $step): string {
    $groupName = $step['section']['group']['name'] ?? ($step['type'] === 'income' ? 'Income' : null);
    return CategoryGroupIcons::forName($groupName);
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Budget Review · <?= View::e($monthLabel) ?> · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
<div class="review-shell" data-goal-contributions="<?= View::e($plannedGoalContributions) ?>" data-csrf-token="<?= View::e($csrfToken) ?>" data-period-month="<?= View::e($monthQuery) ?>">

    <div class="review-topbar">
        <div class="review-topbar-inner">
            <div class="flex items-center justify-between gap-4">
                <a href="/budgets?month=<?= View::e($monthQuery) ?>" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-500 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Exit review
                </a>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400 dark:text-stone-500">Left to budget</div>
                    <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-400" id="reviewRemaining">$0.00</div>
                </div>
            </div>
            <div class="review-progress-track" id="reviewProgressTrack"></div>
            <p class="text-xs text-stone-500 dark:text-stone-400 mt-2" id="reviewStepCaption">&nbsp;</p>
        </div>
    </div>

    <div class="review-stage">

        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><?= View::e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($notice)): ?>
            <div class="alert-success mb-4"><?= View::e($notice) ?></div>
        <?php endif; ?>

        <?php if ($steps === []): ?>
            <div class="card text-center py-12">
                <p class="text-stone-500 dark:text-stone-400 mb-2">No categories to plan yet.</p>
                <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
            </div>
        <?php else: ?>

            <?php foreach ($steps as $i => $step): ?>
                <?php $section = $step['section']; ?>
                <section class="review-step <?= $i === 0 ? 'active' : '' ?>" data-step="<?= $i ?>">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="metric-icon flex-shrink-0" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $stepIcon($step) ?></span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-terracotta-600 dark:text-terracotta-400">
                                <?= $step['type'] === 'income' ? 'Income' : 'Expense group' ?> &middot; <?= $i + 1 ?> of <?= count($steps) ?>
                            </p>
                            <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white truncate"><?= View::e($stepLabel($step)) ?></h1>
                        </div>
                    </div>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-5 mt-2">
                        Every field is pre-filled with a 3-month average so you're confirming numbers, not starting from scratch — adjust anything that's changed.
                    </p>

                    <?php if ($i === 0 && $hasPreviousBudget): ?>
                        <form method="POST" action="/budgets/copy-previous" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <input type="hidden" name="period_month" value="<?= View::e($periodMonth) ?>">
                            <input type="hidden" name="from_review" value="1">
                            <button type="submit" class="btn-secondary">Copy every line from last month instead</button>
                        </form>
                    <?php endif; ?>

                    <div class="card p-0 overflow-hidden">
                        <?php foreach ($section['rows'] as $row): ?>
                            <?php
                            $category = $row['category'];
                            $suggested = $row['planned'] ?? $row['average'];
                            $wasPlanned = $row['planned'] !== null;
                            ?>
                            <div class="review-cat-row">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: <?= View::e($category['color'] ?: '#a8a29e') ?>"></span>
                                        <span class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></span>
                                    </div>
                                    <div class="text-xs text-stone-500 dark:text-stone-400 ml-4">avg <?= Money::format($row['average']) ?>/mo over 3 months</div>
                                </div>
                                <form class="review-item-form" data-autosave="review-item" action="/budgets/items" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <input type="hidden" name="period_month" value="<?= View::e($monthQuery) ?>">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                    <div class="review-cat-input-wrap">
                                        <span class="text-sm text-stone-400 dark:text-stone-500">$</span>
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            name="planned_amount"
                                            class="review-cat-input review-amount-input tabular-nums"
                                            data-type="<?= $step['type'] ?>"
                                            data-last-saved="<?= $wasPlanned ? View::e($row['planned']) : '' ?>"
                                            value="<?= View::e($suggested) ?>"
                                        >
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="review-step" data-step="<?= count($steps) ?>">
                <p class="text-[11px] font-bold uppercase tracking-wide text-terracotta-600 dark:text-terracotta-400 mb-1.5">Review complete</p>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-1"><?= View::e($monthLabel) ?>, planned.</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mb-5">Here's how it lines up. Everything above has already been saved as you went — head back to Budgets any time to fine-tune a single category.</p>

                <div class="stat-hero stat-hero-positive flex items-center justify-between flex-wrap gap-6" id="summaryHero">
                    <span class="stat-hero-icon text-emerald-600" id="summaryHeroIcon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div style="position: relative;">
                        <div class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400 mb-2" id="summaryHeroLabel">Left to budget</div>
                        <div class="text-stone-900 dark:text-white tabular-nums" style="font-size: 2.5rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;" id="summaryRemaining">$0.00</div>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Income minus every category above, minus <?= Money::format($plannedGoalContributions) ?> in planned goal contributions.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="card">
                        <div class="label-text">Income planned</div>
                        <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-400" id="summaryIncome">$0.00</div>
                    </div>
                    <div class="card">
                        <div class="label-text">Expenses planned</div>
                        <div class="text-xl font-semibold tabular-nums text-stone-900 dark:text-white" id="summaryExpense">$0.00</div>
                    </div>
                </div>

                <a href="/budgets?month=<?= View::e($monthQuery) ?>" class="btn-primary inline-block mt-6">Done &mdash; go to Budgets</a>
            </section>

        <?php endif; ?>
    </div>

    <?php if ($steps !== []): ?>
    <div class="review-footer-nav">
        <div class="review-footer-inner">
            <button type="button" class="btn-secondary" id="reviewBackBtn">Back</button>
            <button type="button" class="btn-primary" id="reviewNextBtn">Next</button>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="<?= View::asset('/assets/js/budget-review.js') ?>" defer></script>
</body>
</html>
