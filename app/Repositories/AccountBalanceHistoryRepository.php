<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AccountBalanceHistoryRepository
{
    public function record(int $accountId, int $userId, string $previousBalance, string $newBalance, ?string $note): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO account_balance_history
                (account_id, changed_by_user_id, previous_balance, new_balance, note, created_at)
             VALUES
                (:account_id, :changed_by_user_id, :previous_balance, :new_balance, :note, :created_at)'
        );

        $stmt->execute([
            'account_id' => $accountId,
            'changed_by_user_id' => $userId,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'note' => $note,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Scoped through accounts.household_id so history can never be read
     * across a household boundary, even if the account_id were guessed.
     */
    public function listForAccount(int $accountId, int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT h.previous_balance, h.new_balance, h.note, h.created_at, u.name AS changed_by_name
             FROM account_balance_history h
             INNER JOIN accounts a ON a.id = h.account_id
             INNER JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.account_id = :account_id AND a.household_id = :household_id
             ORDER BY h.created_at DESC
             LIMIT 50'
        );

        $stmt->execute(['account_id' => $accountId, 'household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Full history for a set of accounts in one query (for building a net
     * worth trend across all of a household's accounts without an N+1),
     * ordered so each account's rows are already oldest-first — callers
     * that need "balance as of date X" can stop at the first row past
     * that date. Household scoping is the caller's responsibility since
     * $accountIds is expected to already come from a household-scoped
     * account list, not user input.
     *
     * @param list<int> $accountIds
     */
    public function listForAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT account_id, previous_balance, new_balance, created_at
             FROM account_balance_history
             WHERE account_id IN ({$placeholders})
             ORDER BY account_id, created_at ASC"
        );
        $stmt->execute(array_values($accountIds));

        return $stmt->fetchAll();
    }
}
