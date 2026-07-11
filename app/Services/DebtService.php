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
