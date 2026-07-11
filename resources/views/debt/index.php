<?php

/** @var list<array{account: array, payoff: array|null, utilization: float|null}> $rows */
/** @var string $totalBalance */
/** @var string $totalMinimumPayment */
/** @var float|null $weightedAverageRate */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$typeLabel = [
    'credit_card' => 'Credit Card',
    'mortgage' => 'Mortgage',
    'auto_loan' => 'Auto Loan',
    'student_loan' => 'Student Loan',
    'personal_loan' => 'Personal Loan',
    'other_liability' => 'Other',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt · Finance</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'debt']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Debt</h1>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Every liability account in one place. Edit balances and rates from Accounts.</p>
                </div>

                <?php if ($rows === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400 mb-2">No debt accounts yet.</p>
                        <a href="/accounts/create" class="text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Add a credit card or loan &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div>
                                <div class="label-text">Total debt</div>
                                <div class="text-xl font-semibold text-red-600 dark:text-red-400"><?= Money::format($totalBalance) ?></div>
                            </div>
                            <div>
                                <div class="label-text">Total minimum payments</div>
                                <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalMinimumPayment) ?> <span class="text-sm font-normal text-stone-500 dark:text-stone-400">/ month</span></div>
                            </div>
                            <div>
                                <div class="label-text">Weighted avg. interest rate</div>
                                <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $weightedAverageRate !== null ? $weightedAverageRate . '%' : '—' ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card divide-y divide-stone-100 dark:divide-stone-800">
                        <?php foreach ($rows as $row): ?>
                            <?php $account = $row['account']; $payoff = $row['payoff']; ?>
                            <div class="py-4 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4 mb-2">
                                    <div>
                                        <div class="font-medium text-stone-900 dark:text-white"><?= View::e($account['name']) ?></div>
                                        <div class="text-xs text-stone-500 dark:text-stone-400"><?= View::e($typeLabel[$account['account_type']] ?? $account['account_type']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-red-600 dark:text-red-400"><?= Money::format($account['current_balance']) ?></div>
                                        <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="text-xs font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Edit &rarr;</a>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                    <div>
                                        <div class="text-xs text-stone-400 dark:text-stone-600">Interest rate</div>
                                        <div class="text-stone-700 dark:text-stone-300"><?= $account['interest_rate'] !== null ? $account['interest_rate'] . '%' : '—' ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-stone-400 dark:text-stone-600">Minimum payment</div>
                                        <div class="text-stone-700 dark:text-stone-300"><?= $account['minimum_payment'] !== null ? Money::format($account['minimum_payment']) : '—' ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-stone-400 dark:text-stone-600">Due day</div>
                                        <div class="text-stone-700 dark:text-stone-300"><?= $account['payment_due_day'] !== null ? 'Day ' . (int) $account['payment_due_day'] : '—' ?></div>
                                    </div>
                                    <?php if ($row['utilization'] !== null): ?>
                                        <div>
                                            <div class="text-xs text-stone-400 dark:text-stone-600">Credit utilization</div>
                                            <div class="<?= $row['utilization'] >= 30 ? 'text-red-600 dark:text-red-400' : 'text-stone-700 dark:text-stone-300' ?>"><?= $row['utilization'] ?>%</div>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <div class="text-xs text-stone-400 dark:text-stone-600">Est. payoff</div>
                                            <div class="text-stone-700 dark:text-stone-300">
                                                <?php if ($payoff === null): ?>
                                                    —
                                                <?php elseif (!$payoff['coversInterest']): ?>
                                                    <span class="text-red-600 dark:text-red-400">Payment doesn't cover interest</span>
                                                <?php else: ?>
                                                    <?= View::e($payoff['payoffDate']) ?> (est.)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
