<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Debt payoff math, kept out of controllers/templates per CLAUDE.md's
 * "dedicated calculation or reporting services" guidance. Every result
 * here must be presented as an estimate, never a guarantee, per
 * CLAUDE.md's explicit instruction for the Debt Management feature —
 * these are simple fixed-payment amortization projections, not account
 * for future rate changes, fees, or missed payments.
 */
final class DebtService
{
    /**
     * @return array{months: int, payoffDate: string, coversInterest: true}|array{months: null, payoffDate: null, coversInterest: false}|null
     */
    public function estimatePayoff(string $balance, ?string $annualRatePercent, ?string $minimumPayment): ?array
    {
        if ($annualRatePercent === null || $minimumPayment === null) {
            return null;
        }

        $balanceFloat = (float) $balance;
        $payment = (float) $minimumPayment;

        if ($balanceFloat <= 0 || $payment <= 0) {
            return null;
        }

        $monthlyRate = ((float) $annualRatePercent / 100) / 12;

        if ($monthlyRate <= 0) {
            $months = (int) ceil($balanceFloat / $payment);

            return ['months' => $months, 'payoffDate' => date('Y-m-d', strtotime("+{$months} months")), 'coversInterest' => true];
        }

        $interestPortion = $monthlyRate * $balanceFloat;
        if ($payment <= $interestPortion) {
            return ['months' => null, 'payoffDate' => null, 'coversInterest' => false];
        }

        $months = (int) ceil(-log(1 - ($monthlyRate * $balanceFloat) / $payment) / log(1 + $monthlyRate));

        return ['months' => $months, 'payoffDate' => date('Y-m-d', strtotime("+{$months} months")), 'coversInterest' => true];
    }

    private const MAX_SIMULATION_MONTHS = 600; // 50 years — a safety cap against an under-funded plan looping forever, not a claim any real payoff should take this long

    /**
     * Month-by-month payoff simulation across every debt at once, for the
     * snowball/avalanche comparison. Uses plain floats rather than
     * bcmath — unlike DebtService's other methods and the rest of the
     * app's money handling, this never touches a stored balance; it's a
     * projection over a fixed number of simulated months, in the same
     * spirit as estimatePayoff()'s existing float-based math.
     *
     * Every month: interest accrues on each open balance, minimum
     * payments are applied to every open debt, then whatever's left of
     * the household's total monthly debt budget (sum of minimums plus
     * the extra payment) goes to the highest-priority open debt — and
     * cascades to the next one if that debt's balance is smaller than
     * what's left. As debts close, their minimum payment joins the pool
     * for the rest ("snowballing"), which is the whole point of either
     * strategy.
     *
     * @param list<array{id: int, name: string, balance: string, rate: float, minimum: float}> $debts
     * @param 'snowball'|'avalanche' $strategy
     * @return array{converged: bool, totalMonths: int, totalInterest: float, payoffOrder: list<array{id: int, name: string, month: int|null}>}
     */
    public function simulatePayoff(array $debts, float $extraMonthly, string $strategy): array
    {
        $order = $this->priorityOrder($debts, $strategy);

        $balances = [];
        $monthlyRates = [];
        $minimums = [];
        $names = [];
        foreach ($debts as $debt) {
            $balances[$debt['id']] = (float) $debt['balance'];
            $monthlyRates[$debt['id']] = ($debt['rate'] / 100) / 12;
            $minimums[$debt['id']] = $debt['minimum'];
            $names[$debt['id']] = $debt['name'];
        }

        $totalBudget = array_sum($minimums) + max(0.0, $extraMonthly);
        $totalInterest = 0.0;
        $payoffMonth = [];
        $month = 0;
        $converged = false;

        while ($month < self::MAX_SIMULATION_MONTHS) {
            if ($this->allPaidOff($balances, $order)) {
                $converged = true;
                break;
            }

            $month++;

            foreach ($order as $id) {
                if ($balances[$id] > 0) {
                    $interest = $balances[$id] * $monthlyRates[$id];
                    $balances[$id] += $interest;
                    $totalInterest += $interest;
                }
            }

            $remaining = $totalBudget;

            foreach ($order as $id) {
                if ($balances[$id] > 0) {
                    $pay = min($minimums[$id], $balances[$id], $remaining);
                    $balances[$id] -= $pay;
                    $remaining -= $pay;
                }
            }

            foreach ($order as $id) {
                if ($remaining <= 0) {
                    break;
                }
                if ($balances[$id] > 0) {
                    $pay = min($remaining, $balances[$id]);
                    $balances[$id] -= $pay;
                    $remaining -= $pay;
                }
            }

            foreach ($order as $id) {
                if ($balances[$id] <= 0.005 && !isset($payoffMonth[$id])) {
                    $balances[$id] = 0.0;
                    $payoffMonth[$id] = $month;
                }
            }
        }

        $payoffOrder = [];
        foreach ($order as $id) {
            $payoffOrder[] = ['id' => $id, 'name' => $names[$id], 'month' => $payoffMonth[$id] ?? null];
        }
        usort($payoffOrder, fn (array $a, array $b): int => ($a['month'] ?? PHP_INT_MAX) <=> ($b['month'] ?? PHP_INT_MAX));

        return [
            'converged' => $converged,
            'totalMonths' => $month,
            'totalInterest' => round($totalInterest, 2),
            'payoffOrder' => $payoffOrder,
        ];
    }

    /**
     * @param array<int, float> $balances
     * @param list<int> $order
     */
    private function allPaidOff(array $balances, array $order): bool
    {
        foreach ($order as $id) {
            if ($balances[$id] > 0.005) {
                return false;
            }
        }

        return true;
    }

    /**
     * Snowball: smallest balance first (quick psychological wins).
     * Avalanche: highest interest rate first (minimizes total interest —
     * mathematically optimal, which is why the comparison view highlights
     * the interest difference between the two).
     *
     * @param list<array{id: int, name: string, balance: string, rate: float, minimum: float}> $debts
     * @return list<int>
     */
    private function priorityOrder(array $debts, string $strategy): array
    {
        $sorted = $debts;

        if ($strategy === 'avalanche') {
            usort($sorted, fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);
        } else {
            usort($sorted, fn (array $a, array $b): int => (float) $a['balance'] <=> (float) $b['balance']);
        }

        return array_column($sorted, 'id');
    }

    public function creditUtilization(string $balance, ?string $creditLimit): ?float
    {
        if ($creditLimit === null || (float) $creditLimit <= 0) {
            return null;
        }

        return round(((float) $balance / (float) $creditLimit) * 100, 1);
    }

    /**
     * @param array $accounts liability-type account rows
     * @return array{totalBalance: string, totalMinimumPayment: string, weightedAverageRate: float|null, count: int}
     */
    public function summarize(array $accounts): array
    {
        $totalBalance = '0.00';
        $totalMinimumPayment = '0.00';
        $weightedRateSum = 0.0;

        foreach ($accounts as $account) {
            $totalBalance = bcadd($totalBalance, $account['current_balance'], 2);

            if ($account['minimum_payment'] !== null) {
                $totalMinimumPayment = bcadd($totalMinimumPayment, $account['minimum_payment'], 2);
            }

            if ($account['interest_rate'] !== null) {
                $weightedRateSum += (float) $account['current_balance'] * (float) $account['interest_rate'];
            }
        }

        return [
            'totalBalance' => $totalBalance,
            'totalMinimumPayment' => $totalMinimumPayment,
            'weightedAverageRate' => bccomp($totalBalance, '0.00', 2) > 0 ? round($weightedRateSum / (float) $totalBalance, 2) : null,
            'count' => count($accounts),
        ];
    }
}
