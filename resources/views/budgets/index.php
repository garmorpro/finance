<?php

/** @var string $periodMonth */
/** @var string $monthLabel */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var array $expenseCategories */
/** @var array<int, array> $itemsByCategory */
/** @var array<int, string> $actualByCategory */
/** @var array $unbudgetedSpending */
/** @var string $totalPlanned */
/** @var string $totalSpent */
/** @var string $totalRemaining */
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
$percentFor = function (string $spent, string $planned): int {
    if (bccomp($planned, '0.00', 2) <= 0) {
        return bccomp($spent, '0.00', 2) > 0 ? 100 : 0;
    }
    $percent = (int) bcdiv(bcmul($spent, '100', 2), $planned, 0);
    return min(100, max(0, $percent));
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
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Monthly category budgets.</p>
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

                <?php if ($expenseCategories === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400 mb-2">No expense categories yet.</p>
                        <a href="/settings/categories" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add categories in Settings &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="grid grid-cols-3 gap-6">
                            <div>
                                <div class="label-text">Planned</div>
                                <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalPlanned) ?></div>
                            </div>
                            <div>
                                <div class="label-text">Spent</div>
                                <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalSpent) ?></div>
                            </div>
                            <div>
                                <div class="label-text">Remaining</div>
                                <div class="text-xl font-semibold <?= bccomp($totalRemaining, '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                                    <?= Money::format($totalRemaining) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                        <?php foreach ($expenseCategories as $category): ?>
                            <?php
                            $categoryId = (int) $category['id'];
                            $planned = $itemsByCategory[$categoryId]['planned_amount'] ?? null;
                            $spent = $actualByCategory[$categoryId] ?? '0.00';
                            $remaining = $planned !== null ? bcsub($planned, $spent, 2) : null;
                            $overBudget = $remaining !== null && bccomp($remaining, '0.00', 2) < 0;
                            ?>
                            <div class="py-4 first:pt-0 last:pb-0">
                                <div class="flex items-center justify-between gap-4 mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background-color: <?= View::e($category['color'] ?? '#a8a29e') ?>"></span>
                                        <span class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($category['name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm shrink-0">
                                        <span class="text-stone-500 dark:text-stone-400">
                                            <?= Money::format($spent) ?><?= $planned !== null ? ' of ' . Money::format($planned) : ' spent' ?>
                                        </span>
                                        <?php if ($planned !== null): ?>
                                            <span class="font-medium w-24 text-right <?= $overBudget ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>">
                                                <?= $overBudget ? 'Over by ' . Money::format(bcmul($remaining, '-1', 2)) : Money::format($remaining) . ' left' ?>
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
                                        <div class="h-1.5 rounded-full <?= $overBudget ? 'bg-red-500' : 'bg-terracotta-600' ?>" style="width: <?= $percentFor($spent, $planned) ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($unbudgetedSpending !== []): ?>
                        <div class="card">
                            <h2 class="font-medium text-stone-900 dark:text-white mb-1">Unbudgeted spending</h2>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">These categories had spending this month but no planned amount.</p>
                            <ul class="space-y-2">
                                <?php foreach ($unbudgetedSpending as $row): ?>
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="text-stone-700 dark:text-stone-300"><?= View::e($row['category_name']) ?></span>
                                        <span class="font-medium text-stone-900 dark:text-white"><?= Money::format($row['amount']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
