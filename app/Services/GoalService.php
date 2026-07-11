<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Goal progress math, kept out of controllers/templates per CLAUDE.md's
 * "dedicated calculation or reporting services" guidance. Every estimate
 * here is exactly that — an estimate, never a guaranteed outcome, per
 * CLAUDE.md's explicit instruction for the Goals feature.
 */
final class GoalService
{
    /**
     * Real percentage, uncapped (a goal can be over-funded) — callers
     * doing a progress bar should clamp the display width themselves.
     */
    public function progressPercent(string $current, string $target): float
    {
        if (bccomp($target, '0.00', 2) <= 0) {
            return 0.0;
        }

        return round(((float) $current / (float) $target) * 100, 1);
    }

    /**
     * Projects a completion date from the goal's own stated monthly
     * contribution plan. Returns null if there's nothing to project from
     * (no planned contribution, or the goal is already funded).
     *
     * @return array{months: int, date: string}|null
     */
    public function estimatedCompletion(string $current, string $target, ?string $monthlyContribution): ?array
    {
        if ($monthlyContribution === null || bccomp($monthlyContribution, '0.00', 2) <= 0) {
            return null;
        }

        $remaining = bcsub($target, $current, 2);
        if (bccomp($remaining, '0.00', 2) <= 0) {
            return null;
        }

        $months = (int) ceil((float) $remaining / (float) $monthlyContribution);

        return [
            'months' => $months,
            'date' => date('Y-m-d', strtotime("+{$months} months")),
        ];
    }

    /**
     * The monthly amount needed to hit a target_date, for comparison
     * against the goal's own planned_monthly_contribution. Returns null
     * if there's no target date, it's already past, or the goal is
     * already funded.
     */
    public function suggestedMonthlyContribution(string $current, string $target, ?string $targetDate): ?string
    {
        if ($targetDate === null) {
            return null;
        }

        $remaining = bcsub($target, $current, 2);
        if (bccomp($remaining, '0.00', 2) <= 0) {
            return null;
        }

        $monthsRemaining = $this->monthsUntil($targetDate);
        if ($monthsRemaining <= 0) {
            return null;
        }

        return bcdiv($remaining, (string) $monthsRemaining, 2);
    }

    private function monthsUntil(string $targetDate): int
    {
        $today = new \DateTimeImmutable(gmdate('Y-m-d'));
        $target = new \DateTimeImmutable($targetDate);

        if ($target <= $today) {
            return 0;
        }

        $diff = $today->diff($target);

        return max(1, ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0));
    }
}
