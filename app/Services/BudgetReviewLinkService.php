<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BudgetReviewLinkRepository;

/**
 * Issues and resolves the single-use (or, per household setting,
 * multi-use-until-expiry) magic links behind the budget planning
 * reminder email. A link only ever grants access to one household's
 * one budget review, for one month — see
 * BudgetController::resolveReviewAccess() for how that's enforced on
 * the receiving end.
 */
final class BudgetReviewLinkService
{
    /**
     * @return array{token: string, url: string, expiresAt: string}
     */
    public function issue(int $householdId, int $userId, string $periodMonth, bool $singleUse, int $expiryDays): array
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $expiryDays * 86400);

        (new BudgetReviewLinkRepository())->create($householdId, $userId, $periodMonth, $tokenHash, $singleUse, $expiresAt);

        $url = rtrim($_ENV['APP_URL'] ?? '', '/') . '/budgets/review/link/' . $token;

        return ['token' => $token, 'url' => $url, 'expiresAt' => $expiresAt];
    }

    /**
     * Read-only — who does this token belong to, without spending it.
     *
     * @return array{id: int, householdId: int, userId: int, periodMonth: string}|null
     */
    public function peek(string $token): ?array
    {
        $row = (new BudgetReviewLinkRepository())->findValidByTokenHash(hash('sha256', $token));

        return $row === null ? null : $this->shape($row);
    }

    /**
     * Spends the token. Only call this once the caller has decided
     * access should actually be granted — see BudgetReviewLinkController,
     * which peeks first to branch on "signed in as someone else" without
     * burning a link that isn't theirs to burn.
     */
    public function consume(int $id): bool
    {
        return (new BudgetReviewLinkRepository())->consume($id);
    }

    /**
     * periodMonth stays the full "YYYY-MM-DD" (first of month) the
     * period_month DATE column stores — same format
     * BudgetController::resolveMonth() normalizes to everywhere else,
     * so callers never have to guess whether they're holding a 7- or
     * 10-character month string.
     *
     * @return array{id: int, householdId: int, userId: int, periodMonth: string}
     */
    private function shape(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'householdId' => (int) $row['household_id'],
            'userId' => (int) $row['user_id'],
            'periodMonth' => (string) $row['period_month'],
        ];
    }
}
