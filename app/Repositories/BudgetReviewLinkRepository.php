<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class BudgetReviewLinkRepository
{
    public function create(
        int $householdId,
        int $userId,
        string $periodMonth,
        string $tokenHash,
        bool $singleUse,
        string $expiresAt
    ): int {
        $stmt = Connection::get()->prepare(
            'INSERT INTO budget_review_links
                (household_id, user_id, period_month, token_hash, single_use, expires_at, used_count, created_at)
             VALUES
                (:household_id, :user_id, :period_month, :token_hash, :single_use, :expires_at, 0, :created_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'user_id' => $userId,
            'period_month' => $periodMonth,
            'token_hash' => $tokenHash,
            'single_use' => $singleUse ? 1 : 0,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    /**
     * Read-only lookup — does not spend the link. Still valid means: not
     * expired, and (unlimited-use OR never yet used). Callers that are
     * about to actually grant access call consume() next; this exists
     * separately so a caller can tell "this link belongs to someone
     * else who's currently signed in" apart from "already used up"
     * without spending it in the process of finding out.
     */
    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM budget_review_links
             WHERE token_hash = :token_hash
               AND expires_at > :now
               AND (single_use = 0 OR used_count = 0)
             LIMIT 1'
        );

        $stmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Atomically re-checks the same validity conditions as
     * findValidByTokenHash() and increments used_count in one statement,
     * closing the gap between "looked valid" and "marked used" that two
     * separate queries would leave open to a double-submit or a second
     * tab racing the first. Returns whether the link was actually
     * consumed by this call.
     */
    public function consume(int $id): bool
    {
        $stmt = Connection::get()->prepare(
            'UPDATE budget_review_links
             SET used_count = used_count + 1, last_used_at = :now
             WHERE id = :id
               AND expires_at > :now2
               AND (single_use = 0 OR used_count = 0)'
        );

        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute(['now' => $now, 'now2' => $now, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Cron idempotency check — has this household/user/month combination
     * already had a link issued, so a daily run doesn't re-send (and
     * re-generate a whole new token for) the same reminder every day
     * during its trigger window.
     */
    public function existsForHouseholdUserMonth(int $householdId, int $userId, string $periodMonth): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM budget_review_links
             WHERE household_id = :household_id AND user_id = :user_id AND period_month = :period_month
             LIMIT 1'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'user_id' => $userId,
            'period_month' => $periodMonth,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}
