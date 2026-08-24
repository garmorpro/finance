<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\FieldCipher;

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

    /**
     * Liability accounts (credit cards, loans, etc.) track a positive
     * "amount owed" balance — the mirror image of an asset account's. A
     * charge (negative delta) should increase what's owed instead of
     * decreasing what's in the account, and a payment/credit (positive
     * delta) should decrease it. Every place that moves a signed amount
     * onto an account's balance (creating, editing, deleting, or
     * transferring a transaction; CSV import; recurring items) routes
     * through here instead of assuming asset semantics.
     */
    public static function applyDelta(string $balance, string $delta, string $accountType): string
    {
        return self::isLiability($accountType)
            ? bcsub($balance, $delta, 2)
            : bcadd($balance, $delta, 2);
    }

    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO accounts
                (household_id, created_by_user_id, name, name_encrypted, institution_name,
                 institution_name_encrypted, account_type, current_balance, available_balance,
                 credit_limit, interest_rate, minimum_payment, payment_due_day, original_balance,
                 color, include_in_net_worth, include_in_budget, status, notes, notes_encrypted,
                 created_at, updated_at)
             VALUES
                (:household_id, :created_by_user_id, NULL, :name_encrypted, NULL,
                 :institution_name_encrypted, :account_type, :current_balance, :available_balance,
                 :credit_limit, :interest_rate, :minimum_payment, :payment_due_day, :original_balance,
                 :color, :include_in_net_worth, :include_in_budget, :status, NULL, :notes_encrypted,
                 :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'created_by_user_id' => $userId,
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'institution_name_encrypted' => FieldCipher::encrypt($data['institution_name']),
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
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
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

        return $row === false ? null : self::hydrate($row);
    }

    public function listForHousehold(int $householdId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM accounts WHERE household_id = :household_id AND deleted_at IS NULL';

        if (!$includeArchived) {
            $sql .= " AND status != 'archived'";
        }

        // ORDER BY name here sorts encrypted households' accounts by
        // their now-always-NULL plaintext name column — meaningless, but
        // harmless (a stable no-op ordering key) rather than broken;
        // status/account_type still group things sensibly first. Sorting
        // by real name happens in PHP below, on the decrypted value.
        $sql .= ' ORDER BY status, account_type, name';

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute(['household_id' => $householdId]);

        $rows = array_map(self::hydrate(...), $stmt->fetchAll());

        usort($rows, fn (array $a, array $b): int => [$a['status'], $a['account_type'], $a['name']] <=> [$b['status'], $b['account_type'], $b['name']]);

        return $rows;
    }

    /**
     * Decrypts name/institution_name/notes from their *_encrypted
     * columns, falling back to the old plaintext column for any row not
     * yet run through bin/encrypt-existing-text-fields.php — this makes
     * deploying this code and running that backfill script safe in
     * either order, nothing breaks in between. The raw *_encrypted
     * binary blobs are removed from the returned row entirely so no
     * caller ever accidentally tries to treat/display/json_encode raw
     * ciphertext bytes as text.
     */
    private static function hydrate(array $row): array
    {
        $row['name'] = FieldCipher::decryptOrFallback($row['name_encrypted'], $row['name']);
        $row['institution_name'] = FieldCipher::decryptOrFallback($row['institution_name_encrypted'], $row['institution_name']);
        $row['notes'] = FieldCipher::decryptOrFallback($row['notes_encrypted'], $row['notes']);

        unset($row['name_encrypted'], $row['institution_name_encrypted'], $row['notes_encrypted']);

        return $row;
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
        $assetCount = 0;
        $liabilityCount = 0;

        foreach ($accounts as $account) {
            if ((int) $account['include_in_net_worth'] !== 1) {
                continue;
            }

            if (self::isLiability($account['account_type'])) {
                $liabilities = bcadd($liabilities, $account['current_balance'], 2);
                $liabilityCount++;
            } else {
                $assets = bcadd($assets, $account['current_balance'], 2);
                $assetCount++;
            }
        }

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'net' => bcsub($assets, $liabilities, 2),
            'count' => count($accounts),
            'assetCount' => $assetCount,
            'liabilityCount' => $liabilityCount,
        ];
    }

    public function update(int $accountId, int $householdId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE accounts SET
                name = NULL,
                name_encrypted = :name_encrypted,
                institution_name = NULL,
                institution_name_encrypted = :institution_name_encrypted,
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
                notes = NULL,
                notes_encrypted = :notes_encrypted,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        // Every update also clears the old plaintext columns, not just
        // the encrypted ones — editing an account is what naturally
        // migrates it off plaintext for households that never run
        // bin/encrypt-existing-text-fields.php at all.
        $stmt->execute([
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'institution_name_encrypted' => FieldCipher::encrypt($data['institution_name']),
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
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
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
