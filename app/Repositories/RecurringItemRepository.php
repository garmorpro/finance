<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\FieldCipher;

final class RecurringItemRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO recurring_items
                (household_id, account_id, category_id, created_by_user_id, name, name_encrypted,
                 recurring_type, expected_amount, frequency, next_due_date, auto_pay, status, notes,
                 notes_encrypted, created_at, updated_at)
             VALUES
                (:household_id, :account_id, :category_id, :created_by_user_id, NULL, :name_encrypted,
                 :recurring_type, :expected_amount, :frequency, :next_due_date, :auto_pay, :status, NULL,
                 :notes_encrypted, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'created_by_user_id' => $userId,
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'recurring_type' => $data['recurring_type'],
            'expected_amount' => $data['expected_amount'],
            'frequency' => $data['frequency'],
            'next_due_date' => $data['next_due_date'],
            'auto_pay' => $data['auto_pay'] ? 1 : 0,
            'status' => 'active',
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findById(int $recurringItemId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM recurring_items WHERE id = :id AND household_id = :household_id LIMIT 1'
        );

        $stmt->execute(['id' => $recurringItemId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $recurringItemId, int $householdId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE recurring_items SET
                account_id = :account_id,
                category_id = :category_id,
                name = NULL,
                name_encrypted = :name_encrypted,
                recurring_type = :recurring_type,
                expected_amount = :expected_amount,
                frequency = :frequency,
                next_due_date = :next_due_date,
                auto_pay = :auto_pay,
                notes = NULL,
                notes_encrypted = :notes_encrypted,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'name_encrypted' => FieldCipher::encrypt($data['name']),
            'recurring_type' => $data['recurring_type'],
            'expected_amount' => $data['expected_amount'],
            'frequency' => $data['frequency'],
            'next_due_date' => $data['next_due_date'],
            'auto_pay' => $data['auto_pay'] ? 1 : 0,
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $recurringItemId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Advances next_due_date and, when the paid amount differs from the
     * expected one, updates expected_amount to match — bills creep, and
     * this keeps the monthly recurring-cost summary accurate going
     * forward. The caller is responsible for logging the price change to
     * the audit log when this happens.
     */
    public function markPaid(int $recurringItemId, int $householdId, string $newNextDueDate, string $newExpectedAmount): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE recurring_items SET next_due_date = :next_due_date, expected_amount = :expected_amount, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'next_due_date' => $newNextDueDate,
            'expected_amount' => $newExpectedAmount,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $recurringItemId,
            'household_id' => $householdId,
        ]);
    }

    public function setStatus(int $recurringItemId, int $householdId, string $status): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE recurring_items SET status = :status, canceled_at = :canceled_at, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'status' => $status,
            'canceled_at' => $status === 'canceled' ? gmdate('Y-m-d H:i:s') : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $recurringItemId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * All recurring items for the household, joined with account/category
     * display names, ordered so overdue/soonest-due items sort first
     * within active items, with canceled ones last.
     */
    public function listForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT r.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name
             FROM recurring_items r
             INNER JOIN accounts a ON a.id = r.account_id
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.household_id = :household_id'
        );

        $stmt->execute(['household_id' => $householdId]);

        $rows = array_map(self::hydrate(...), $stmt->fetchAll());

        // Replicates the original SQL ORDER BY (active first, then
        // next_due_date, then name) in PHP — name is now only decrypted
        // after the fetch, so SQL can no longer sort by it directly.
        usort($rows, function (array $a, array $b): int {
            $rankA = [$a['status'] === 'active' ? 0 : 1, $a['next_due_date'], $a['name']];
            $rankB = [$b['status'] === 'active' ? 0 : 1, $b['next_due_date'], $b['name']];

            return $rankA <=> $rankB;
        });

        return $rows;
    }

    /**
     * The soonest-due active items, for the dashboard tile — includes
     * overdue ones (they still sort first since next_due_date is in the
     * past), capped to a short preview list.
     */
    public function upcomingForHousehold(int $householdId, int $limit = 5): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT r.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name
             FROM recurring_items r
             INNER JOIN accounts a ON a.id = r.account_id
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.household_id = :household_id AND r.status = "active"
             ORDER BY r.next_due_date
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['household_id' => $householdId]);

        return array_map(self::hydrate(...), $stmt->fetchAll());
    }

    /**
     * Decrypts name/notes from their *_encrypted columns, falling back
     * to the old plaintext column for any row not yet run through
     * bin/encrypt-existing-text-fields.php, and (when the row came from
     * a JOIN against accounts) the linked account's name the same way —
     * see AccountRepository for why a JOIN can no longer read that
     * column directly. Raw *_encrypted binary blobs are removed from the
     * returned row entirely.
     */
    private static function hydrate(array $row): array
    {
        $row['name'] = FieldCipher::decryptOrFallback($row['name_encrypted'], $row['name']);
        $row['notes'] = FieldCipher::decryptOrFallback($row['notes_encrypted'], $row['notes']);
        unset($row['name_encrypted'], $row['notes_encrypted']);

        if (array_key_exists('account_name_encrypted', $row)) {
            $row['account_name'] = FieldCipher::decryptOrFallback($row['account_name_encrypted'], $row['account_name']);
            unset($row['account_name_encrypted']);
        }

        return $row;
    }

    /**
     * @return array{overdue: int, monthlyExpense: string, monthlyIncome: string}
     */
    public function summaryForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT recurring_type, frequency, expected_amount, next_due_date
             FROM recurring_items
             WHERE household_id = :household_id AND status = "active"'
        );

        $stmt->execute(['household_id' => $householdId]);

        $today = gmdate('Y-m-d');
        $overdue = 0;
        $monthlyExpense = '0.00';
        $monthlyIncome = '0.00';

        foreach ($stmt->fetchAll() as $row) {
            if ($row['next_due_date'] < $today) {
                $overdue++;
            }

            $monthly = self::monthlyEquivalent($row['frequency'], $row['expected_amount']);
            if ($row['recurring_type'] === 'expense') {
                $monthlyExpense = bcadd($monthlyExpense, $monthly, 2);
            } else {
                $monthlyIncome = bcadd($monthlyIncome, $monthly, 2);
            }
        }

        return ['overdue' => $overdue, 'monthlyExpense' => $monthlyExpense, 'monthlyIncome' => $monthlyIncome];
    }

    /**
     * Normalizes any supported frequency to its average monthly-equivalent
     * cost, for the "monthly recurring cost" summary. Weekly/biweekly use
     * the average number of such periods per year (52/26) divided by 12
     * rather than a flat x4, since months aren't exactly 4 weeks.
     */
    public static function monthlyEquivalent(string $frequency, string $amount): string
    {
        return match ($frequency) {
            'weekly' => bcdiv(bcmul($amount, '52', 4), '12', 2),
            'biweekly' => bcdiv(bcmul($amount, '26', 4), '12', 2),
            'quarterly' => bcdiv($amount, '3', 2),
            'annually' => bcdiv($amount, '12', 2),
            default => $amount,
        };
    }

    public static function advanceDueDate(string $dueDate, string $frequency): string
    {
        $interval = match ($frequency) {
            'weekly' => '+1 week',
            'biweekly' => '+2 weeks',
            'monthly' => '+1 month',
            'quarterly' => '+3 months',
            'annually' => '+1 year',
            default => '+1 month',
        };

        return date('Y-m-d', strtotime($dueDate . ' ' . $interval));
    }
}
