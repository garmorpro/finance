<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AccountRepository
{
    public const TYPES = [
        'checking', 'savings', 'cash', 'credit_card', 'mortgage', 'auto_loan',
        'student_loan', 'personal_loan', 'investment', 'retirement',
        'property', 'vehicle', 'other_asset', 'other_liability',
    ];

    private const LIABILITY_TYPES = [
        'credit_card', 'mortgage', 'auto_loan', 'student_loan', 'personal_loan', 'other_liability',
    ];

    public static function isLiability(string $accountType): bool
    {
        return in_array($accountType, self::LIABILITY_TYPES, true);
    }

    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO accounts
                (household_id, created_by_user_id, name, institution_name, account_type,
                 current_balance, available_balance, credit_limit, interest_rate,
                 minimum_payment, payment_due_day, original_balance, color,
                 include_in_net_worth, include_in_budget, status, notes, created_at, updated_at)
             VALUES
                (:household_id, :created_by_user_id, :name, :institution_name, :account_type,
                 :current_balance, :available_balance, :credit_limit, :interest_rate,
                 :minimum_payment, :payment_due_day, :original_balance, :color,
                 :include_in_net_worth, :include_in_budget, :status, :notes, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'created_by_user_id' => $userId,
            'name' => $data['name'],
            'institution_name' => $data['institution_name'],
            'account_type' => $data['account_type'],
            'current_balance' => $data['current_balance'],
            'available_balance' => $data['available_balance'],
            'credit_limit' => $data['credit_limit'],
            'interest_rate' => $data['interest_rate'],
            'minimum_payment' => $data['minimum_payment'],
            'payment_due_day' => $data['payment_due_day'],
            'original_balance' => $data['original_balance'],
            'color' => $data['color'],
            'include_in_net_worth' => $data['include_in_net_worth'] ? 1 : 0,
            'include_in_budget' => $data['include_in_budget'] ? 1 : 0,
            'status' => 'active',
            'notes' => $data['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $accountId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM accounts
             WHERE id = :id AND household_id = :household_id AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(['id' => $accountId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForHousehold(int $householdId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM accounts WHERE household_id = :household_id AND deleted_at IS NULL';

        if (!$includeArchived) {
            $sql .= " AND status != 'archived'";
        }

        $sql .= ' ORDER BY status, account_type, name';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Assets/liabilities totals for accounts flagged include_in_net_worth.
     * Uses bcmath (decimal-safe) throughout — never binary float arithmetic
     * on currency, per CLAUDE.md's financial calculation rules.
     *
     * @return array{assets: string, liabilities: string, net: string, count: int}
     */
    public function netWorthSummary(int $householdId): array
    {
        $accounts = $this->listForHousehold($householdId);

        $assets = '0.00';
        $liabilities = '0.00';

        foreach ($accounts as $account) {
            if ((int) $account['include_in_net_worth'] !== 1) {
                continue;
            }

            if (self::isLiability($account['account_type'])) {
                $liabilities = bcadd($liabilities, $account['current_balance'], 2);
            } else {
                $assets = bcadd($assets, $account['current_balance'], 2);
            }
        }

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'net' => bcsub($assets, $liabilities, 2),
            'count' => count($accounts),
        ];
    }

    public function update(int $accountId, int $householdId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE accounts SET
                name = :name,
                institution_name = :institution_name,
                account_type = :account_type,
                available_balance = :available_balance,
                credit_limit = :credit_limit,
                interest_rate = :interest_rate,
                minimum_payment = :minimum_payment,
                payment_due_day = :payment_due_day,
                original_balance = :original_balance,
                color = :color,
                include_in_net_worth = :include_in_net_worth,
                include_in_budget = :include_in_budget,
                notes = :notes,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'name' => $data['name'],
            'institution_name' => $data['institution_name'],
            'account_type' => $data['account_type'],
            'available_balance' => $data['available_balance'],
            'credit_limit' => $data['credit_limit'],
            'interest_rate' => $data['interest_rate'],
            'minimum_payment' => $data['minimum_payment'],
            'payment_due_day' => $data['payment_due_day'],
            'original_balance' => $data['original_balance'],
            'color' => $data['color'],
            'include_in_net_worth' => $data['include_in_net_worth'] ? 1 : 0,
            'include_in_budget' => $data['include_in_budget'] ? 1 : 0,
            'notes' => $data['notes'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $accountId,
            'household_id' => $householdId,
        ]);
    }

    public function updateBalance(int $accountId, int $householdId, string $newBalance): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE accounts SET current_balance = :balance, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'balance' => $newBalance,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $accountId,
            'household_id' => $householdId,
        ]);
    }

    public function setStatus(int $accountId, int $householdId, string $status): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE accounts SET status = :status, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $accountId,
            'household_id' => $householdId,
        ]);
    }
}
