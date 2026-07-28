<?php

/** @var list<array> $overdue */
/** @var list<array> $upcoming */
/** @var list<array> $canceled */
/** @var array{overdue: int, monthlyExpense: string, monthlyIncome: string} $summary */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $notice */

use App\Support\Money;
use App\Support\View;

$today = gmdate('Y-m-d');

$frequencyLabel = [
    'weekly' => 'Weekly',
    'biweekly' => 'Every 2 weeks',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'annually' => 'Annually',
];

$dueLabel = function (string $dueDate) use ($today): string {
    $days = (int) ((strtotime($dueDate) - strtotime($today)) / 86400);
    if ($days === 0) {
        return 'Due today';
    }
    if ($days < 0) {
        return abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue';
    }
    return 'Due in ' . $days . ' day' . ($days === 1 ? '' : 's');
};

$renderRow = function (array $item, bool $isOverdue) use ($csrfToken, $frequencyLabel, $dueLabel): void {
    ?>
    <div class="py-4 first:pt-0 last:pb-0">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($item['name']) ?></span>
                    <?php if ((int) $item['auto_pay'] === 1): ?>
                        <span class="badge">Auto-pay</span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-stone-500 dark:text-stone-400 truncate">
                    <?= View::e($item['account_name']) ?>
                    <?php if ($item['category_name'] !== null): ?>
                        &middot; <?= View::e($item['category_name']) ?>
                    <?php endif; ?>
                    &middot; <?= View::e($frequencyLabel[$item['frequency']] ?? $item['frequency']) ?>
                </div>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right">
                    <div class="font-medium <?= $item['recurring_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-stone-900 dark:text-white' ?>">
                        <?= Money::format($item['expected_amount']) ?>
                    </div>
                    <div class="text-xs <?= $isOverdue ? 'text-red-600 dark:text-red-400 font-medium' : 'text-stone-500 dark:text-stone-400' ?>">
                        <?= View::e($dueLabel($item['next_due_date'])) ?>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="/recurring/<?= (int) $item['id'] ?>/pay" class="btn-primary">Mark paid</a>
                    <a href="/recurring/<?= (int) $item['id'] ?>/edit" class="btn-secondary">Edit</a>
                </div>
            </div>
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
    <title>Recurring · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'recurring']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Recurring</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Bills, subscriptions, and other recurring income or expenses.</p>
                    </div>
                    <a href="/recurring/create" class="btn-primary">Add recurring item</a>
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
                            <div class="label-text">Overdue</div>
                            <div class="text-xl font-semibold <?= $summary['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= $summary['overdue'] ?></div>
                        </div>
                        <div>
                            <div class="label-text">Monthly recurring expenses</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($summary['monthlyExpense']) ?></div>
                        </div>
                        <div>
                            <div class="label-text">Monthly recurring income</div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($summary['monthlyIncome']) ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($overdue !== []): ?>
                    <div>
                        <h2 class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wide mb-3">Overdue</h2>
                        <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                            <?php foreach ($overdue as $item): ?>
                                <?php $renderRow($item, true); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-sm font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wide mb-3">Upcoming</h2>
                    <?php if ($upcoming === []): ?>
                        <div class="card text-center py-10">
                            <p class="text-stone-500 dark:text-stone-400 mb-2">No upcoming recurring items.</p>
                            <a href="/recurring/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add one &rarr;</a>
                        </div>
                    <?php else: ?>
                        <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                            <?php foreach ($upcoming as $item): ?>
                                <?php $renderRow($item, false); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($canceled !== []): ?>
                    <div>
                        <h2 class="text-sm font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wide mb-3">Canceled</h2>
                        <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                            <?php foreach ($canceled as $item): ?>
                                <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 opacity-60">
                                    <div class="min-w-0">
                                        <div class="font-medium text-stone-900 dark:text-white truncate"><?= View::e($item['name']) ?></div>
                                        <div class="text-xs text-stone-500 dark:text-stone-400"><?= Money::format($item['expected_amount']) ?> &middot; <?= View::e($frequencyLabel[$item['frequency']] ?? $item['frequency']) ?></div>
                                    </div>
                                    <form method="POST" action="/recurring/<?= (int) $item['id'] ?>/reactivate">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button type="submit" class="btn-secondary">Reactivate</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
