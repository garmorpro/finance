<?php

/** @var list<array> $active */
/** @var list<array> $completed */
/** @var list<array> $archived */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

$goalTypeLabel = [
    'emergency_fund' => 'Emergency Fund',
    'vacation' => 'Vacation',
    'home_project' => 'Home Project',
    'vehicle' => 'Vehicle',
    'baby_expenses' => 'Baby Expenses',
    'debt_payoff' => 'Debt Payoff',
    'investment_target' => 'Investment Target',
    'mortgage_payoff' => 'Mortgage Payoff',
    'general_savings' => 'General Savings',
];

$renderCard = function (array $goal) use ($goalTypeLabel): void {
    $barWidth = max(0, min(100, $goal['progressPercent']));
    ?>
    <a href="/goals/<?= (int) $goal['id'] ?>/edit" class="card block hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-1">
            <div>
                <div class="font-medium text-stone-900 dark:text-white"><?= View::e($goal['name']) ?></div>
                <div class="text-xs text-stone-500 dark:text-stone-400"><?= View::e($goalTypeLabel[$goal['goal_type']] ?? $goal['goal_type']) ?></div>
            </div>
            <span class="badge"><?= $goal['progressPercent'] ?>%</span>
        </div>
        <div class="w-full bg-stone-100 dark:bg-stone-800 rounded-full h-1.5 my-3">
            <div class="h-1.5 rounded-full bg-terracotta-600" style="width: <?= $barWidth ?>%"></div>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-stone-500 dark:text-stone-400"><?= Money::format($goal['current_amount']) ?> of <?= Money::format($goal['target_amount']) ?></span>
            <?php if ($goal['target_date'] !== null): ?>
                <span class="text-stone-500 dark:text-stone-400">by <?= View::e($goal['target_date']) ?></span>
            <?php endif; ?>
        </div>
    </a>
    <?php
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goals · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'goals']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Goals</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Savings targets, tracked with manual contributions.</p>
                    </div>
                    <a href="/goals/create" class="btn-primary">Add goal</a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= View::e($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert-success"><?= View::e($notice) ?></div>
                <?php endif; ?>

                <?php if ($active === [] && $completed === [] && $archived === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400 mb-2">No goals yet.</p>
                        <a href="/goals/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add your first goal &rarr;</a>
                    </div>
                <?php else: ?>
                    <?php if ($active !== []): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($active as $goal): ?>
                                <?php $renderCard($goal); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($completed !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wide mb-3">Completed</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($completed as $goal): ?>
                                    <?php $renderCard($goal); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($archived !== []): ?>
                        <div>
                            <h2 class="text-sm font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wide mb-3">Archived</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 opacity-60">
                                <?php foreach ($archived as $goal): ?>
                                    <?php $renderCard($goal); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
