<?php

/** @var list<array{account: array, payoff: array|null, utilization: float|null}> $rows */
/** @var string $totalBalance */
/** @var string $totalMinimumPayment */
/** @var float|null $weightedAverageRate */
/** @var float $extraMonthly */
/** @var array{snowball: array, avalanche: array}|null $comparison */
/** @var int $comparableDebtCount */
/** @var string $csrfToken */

use App\Support\AccountTypeIcons;
use App\Support\Money;
use App\Support\View;

$strategyLabels = ['snowball' => 'Snowball', 'avalanche' => 'Avalanche'];
$strategyBlurbs = ['snowball' => 'Smallest balance first', 'avalanche' => 'Highest interest rate first'];

$calendarIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$percentIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>';

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
    <title>Debt · MyCFO+</title>
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
                    <div class="stat-hero stat-hero-negative flex items-center justify-between flex-wrap gap-6">
                        <span class="stat-hero-icon text-red-600">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </span>
                        <div style="position: relative;">
                            <div class="text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-400 mb-2">Total Debt</div>
                            <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($totalBalance) ?></div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Combined balance across <?= count($rows) ?> debt account<?= count($rows) === 1 ? '' : 's' ?>.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="card">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $calendarIcon ?></span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Total Minimum Payments</div>
                                    <div class="text-xs text-stone-500 dark:text-stone-400">Due across all debts</div>
                                </div>
                            </div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totalMinimumPayment) ?> <span class="text-sm font-normal text-stone-500 dark:text-stone-400">/ month</span></div>
                        </div>
                        <div class="card">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $percentIcon ?></span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Weighted Avg. Rate</div>
                                    <div class="text-xs text-stone-500 dark:text-stone-400">Weighted by balance</div>
                                </div>
                            </div>
                            <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $weightedAverageRate !== null ? $weightedAverageRate . '%' : '—' ?></div>
                        </div>
                    </div>

                    <div class="card">
                        <h2 class="font-medium text-stone-900 dark:text-white mb-1">Payoff comparison</h2>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mb-4">Snowball (smallest balance first, for quick wins) vs. avalanche (highest interest rate first, minimizes total interest) — paying every debt's minimum plus any extra thrown at whichever debt is up next. Estimates only: assumes today's rates and balances stay fixed, payments are made on time, and nothing new is charged.</p>

                        <?php if ($comparableDebtCount < 2): ?>
                            <p class="text-sm text-stone-500 dark:text-stone-400">Add a second debt account to compare payoff strategies.</p>
                        <?php else: ?>
                            <form method="GET" action="/debt" class="flex items-end gap-3 mb-6">
                                <div>
                                    <label for="extra" class="field-label">Extra monthly payment</label>
                                    <input type="text" inputmode="decimal" id="extra" name="extra" value="<?= View::e(number_format($extraMonthly, 2, '.', '')) ?>" class="field-input" placeholder="0.00">
                                </div>
                                <button type="submit" class="btn-secondary">Recalculate</button>
                            </form>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($strategyLabels as $key => $label): ?>
                                    <?php $result = $comparison[$key]; ?>
                                    <div class="rounded-xl border border-stone-200 dark:border-stone-800 p-4">
                                        <h3 class="font-semibold text-stone-900 dark:text-white mb-1"><?= View::e($label) ?></h3>
                                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-3"><?= View::e($strategyBlurbs[$key]) ?></p>

                                        <?php if (!$result['converged']): ?>
                                            <p class="text-sm text-red-600 dark:text-red-400">At this payment level, this won't be fully paid off within 50 years. Try a larger extra payment.</p>
                                        <?php else: ?>
                                            <div class="space-y-2 mb-3">
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-stone-500 dark:text-stone-400">Debt-free in</span>
                                                    <span class="font-medium text-stone-900 dark:text-white"><?= $result['totalMonths'] ?> mo. (<?= View::e(date('M Y', strtotime('+' . $result['totalMonths'] . ' months'))) ?>, est.)</span>
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-stone-500 dark:text-stone-400">Total interest (est.)</span>
                                                    <span class="font-medium text-stone-900 dark:text-white"><?= Money::format((string) $result['totalInterest']) ?></span>
                                                </div>
                                            </div>
                                            <div class="text-xs text-stone-500 dark:text-stone-400 uppercase tracking-wide mb-1">Payoff order</div>
                                            <ol class="text-sm text-stone-700 dark:text-stone-300 space-y-0.5">
                                                <?php foreach ($result['payoffOrder'] as $i => $debt): ?>
                                                    <li><?= $i + 1 ?>. <?= View::e($debt['name']) ?> &mdash; <?= $debt['month'] !== null ? 'month ' . $debt['month'] : 'not paid off' ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($comparison['snowball']['converged'] && $comparison['avalanche']['converged']): ?>
                                <?php $interestDiff = $comparison['snowball']['totalInterest'] - $comparison['avalanche']['totalInterest']; ?>
                                <?php if ($interestDiff > 0.01): ?>
                                    <p class="text-sm text-emerald-700 dark:text-emerald-400 mt-4">Avalanche saves an estimated <?= Money::format((string) $interestDiff) ?> in interest versus snowball, for this household's current balances and rates.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($rows as $row): ?>
                            <?php $account = $row['account']; $payoff = $row['payoff']; $accountColor = $account['color'] ?: '#a8a29e'; ?>
                            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="account-card" style="background-color: <?= View::e($accountColor) ?>;">
                                <div class="flex items-start justify-between">
                                    <span class="account-card-icon"><?= AccountTypeIcons::svg($account['account_type']) ?></span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-white/70"><?= View::e($typeLabel[$account['account_type']] ?? $account['account_type']) ?></span>
                                </div>

                                <div class="min-w-0">
                                    <div class="font-semibold truncate"><?= View::e($account['name']) ?></div>
                                    <span class="text-sm tracking-widest text-white/60" aria-hidden="true">&bull;&bull;&bull;&bull;</span>
                                    <div class="text-2xl font-semibold mt-1"><?= Money::format($account['current_balance']) ?></div>
                                </div>

                                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs border-t border-white/15 pt-3 mt-1">
                                    <div>
                                        <div class="text-white/60">Interest rate</div>
                                        <div class="font-medium"><?= $account['interest_rate'] !== null ? $account['interest_rate'] . '%' : '—' ?></div>
                                    </div>
                                    <div>
                                        <div class="text-white/60">Min. payment</div>
                                        <div class="font-medium"><?= $account['minimum_payment'] !== null ? Money::format($account['minimum_payment']) : '—' ?></div>
                                    </div>
                                    <div>
                                        <div class="text-white/60">Due day</div>
                                        <div class="font-medium"><?= $account['payment_due_day'] !== null ? 'Day ' . (int) $account['payment_due_day'] : '—' ?></div>
                                    </div>
                                    <?php if ($row['utilization'] !== null): ?>
                                        <div>
                                            <div class="text-white/60">Utilization</div>
                                            <div class="font-medium flex items-center gap-1.5">
                                                <?php if ($row['utilization'] >= 30): ?>
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0" title="30% or higher"></span>
                                                <?php endif; ?>
                                                <?= $row['utilization'] ?>%
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <div class="text-white/60">Est. payoff</div>
                                            <div class="font-medium">
                                                <?php if ($payoff === null): ?>
                                                    —
                                                <?php elseif (!$payoff['coversInterest']): ?>
                                                    <span class="flex items-center gap-1.5"><span class="inline-block w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0" title="Payment doesn't cover interest"></span>Too low</span>
                                                <?php else: ?>
                                                    <?= View::e($payoff['payoffDate']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
