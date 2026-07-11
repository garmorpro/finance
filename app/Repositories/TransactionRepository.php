<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class TransactionRepository
{
    private const PER_PAGE = 50;

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO transactions
                (household_id, account_id, category_id, created_by_user_id, last_edited_by_user_id,
                 transaction_type, transaction_date, amount, payee, notes,
                 is_reviewed, exclude_from_budget, exclude_from_reports, created_at, updated_at)
             VALUES
                (:household_id, :account_id, :category_id, :created_by_user_id, :last_edited_by_user_id,
                 :transaction_type, :transaction_date, :amount, :payee, :notes,
                 :is_reviewed, :exclude_from_budget, :exclude_from_reports, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'created_by_user_id' => $userId,
            'last_edited_by_user_id' => $userId,
            'transaction_type' => $data['transaction_type'],
            'transaction_date' => $data['transaction_date'],
            'amount' => $data['signed_amount'],
            'payee' => $data['payee'],
            'notes' => $data['notes'],
            'is_reviewed' => $data['is_reviewed'] ? 1 : 0,
            'exclude_from_budget' => $data['exclude_from_budget'] ? 1 : 0,
            'exclude_from_reports' => $data['exclude_from_reports'] ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    /**
     * Links two transaction rows as the two sides of one transfer. Called
     * after both rows exist (can't reference an ID that doesn't exist yet),
     * so this is always the second step of a two-insert-then-link sequence.
     */
    public function linkTransferPair(int $transactionId, int $householdId, int $pairId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET transfer_pair_id = :pair_id, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'pair_id' => $pairId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);
    }

    public function findById(int $transactionId, int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM transactions WHERE id = :id AND household_id = :household_id AND deleted_at IS NULL LIMIT 1'
        );

        $stmt->execute(['id' => $transactionId, 'household_id' => $householdId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Heuristic duplicate check for CSV import: same account, date, amount,
     * and payee already exists. Not foolproof (a household could
     * legitimately buy coffee at the same place twice in one day for the
     * same price), but catches the overwhelmingly common case of
     * re-importing an overlapping date range from a bank export.
     */
    public function existsSimilar(int $householdId, int $accountId, string $date, string $signedAmount, string $payee): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM transactions
             WHERE household_id = :household_id AND account_id = :account_id
               AND transaction_date = :date AND amount = :amount AND payee = :payee
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'account_id' => $accountId,
            'date' => $date,
            'amount' => $signedAmount,
            'payee' => $payee,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $transactionId, int $householdId, int $userId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET
                account_id = :account_id,
                category_id = :category_id,
                last_edited_by_user_id = :last_edited_by_user_id,
                transaction_type = :transaction_type,
                transaction_date = :transaction_date,
                amount = :amount,
                payee = :payee,
                notes = :notes,
                is_reviewed = :is_reviewed,
                exclude_from_budget = :exclude_from_budget,
                exclude_from_reports = :exclude_from_reports,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'last_edited_by_user_id' => $userId,
            'transaction_type' => $data['transaction_type'],
            'transaction_date' => $data['transaction_date'],
            'amount' => $data['signed_amount'],
            'payee' => $data['payee'],
            'notes' => $data['notes'],
            'is_reviewed' => $data['is_reviewed'] ? 1 : 0,
            'exclude_from_budget' => $data['exclude_from_budget'] ? 1 : 0,
            'exclude_from_reports' => $data['exclude_from_reports'] ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Narrow update used for transfers, which only allow editing notes and
     * reviewed status — changing the amount or account on one side of a
     * transfer without touching the other would desync both balances.
     */
    public function updateNotesAndReviewed(int $transactionId, int $householdId, int $userId, ?string $notes, bool $isReviewed): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET
                notes = :notes,
                is_reviewed = :is_reviewed,
                last_edited_by_user_id = :last_edited_by_user_id,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'notes' => $notes,
            'is_reviewed' => $isReviewed ? 1 : 0,
            'last_edited_by_user_id' => $userId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);
    }

    public function softDelete(int $transactionId, int $householdId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET deleted_at = :deleted_at, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: array, total: int, page: int, perPage: int}
     */
    public function listForHousehold(int $householdId, array $filters, int $page = 1): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        $countStmt = Connection::get()->prepare("SELECT COUNT(*) FROM transactions t {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT t.*, a.name AS account_name, c.name AS category_name
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
        ];
    }

    /**
     * Unpaginated variant for CSV export — same filters as the list view,
     * but every matching row, capped at a hard ceiling so an export can't
     * be used to pull an unbounded result set.
     *
     * @param array<string, mixed> $filters
     */
    public function exportForHousehold(int $householdId, array $filters): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);

        $sql = "SELECT t.*, a.name AS account_name, c.name AS category_name
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT 20000";

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function recentForHousehold(int $householdId, int $limit = 5): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, a.name AS account_name, c.name AS category_name
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.household_id = :household_id AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Every unreviewed transaction for the household, most recent first —
     * the review queue. Capped so a household that never reviews anything
     * can't load an unbounded result set; unreviewedCounts() reports the
     * true total so the UI can note when the cap was hit.
     */
    public function unreviewedForHousehold(int $householdId, int $limit = 500): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, a.name AS account_name, c.name AS category_name
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.household_id = :household_id AND t.deleted_at IS NULL AND t.is_reviewed = 0
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{total: int, before_today: int}
     */
    public function unreviewedCounts(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN transaction_date < :today THEN 1 ELSE 0 END) AS before_today
             FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND is_reviewed = 0'
        );

        $stmt->execute(['household_id' => $householdId, 'today' => gmdate('Y-m-d')]);

        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'before_today' => (int) ($row['before_today'] ?? 0),
        ];
    }

    public function markReviewed(int $transactionId, int $householdId, int $userId): bool
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET is_reviewed = 1, last_edited_by_user_id = :user_id, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id AND deleted_at IS NULL'
        );

        $stmt->execute([
            'user_id' => $userId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Marks every currently-unreviewed transaction in the household as
     * reviewed in one statement. Returns the number of rows affected so
     * the caller can report it back to the user.
     */
    public function markAllReviewed(int $householdId, int $userId): int
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET is_reviewed = 1, last_edited_by_user_id = :user_id, updated_at = :updated_at
             WHERE household_id = :household_id AND deleted_at IS NULL AND is_reviewed = 0'
        );

        $stmt->execute([
            'user_id' => $userId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'household_id' => $householdId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(int $householdId, array $filters): array
    {
        $clauses = ['t.household_id = :household_id', 't.deleted_at IS NULL'];
        $params = ['household_id' => $householdId];

        if (!empty($filters['account_id'])) {
            $clauses[] = 't.account_id = :account_id';
            $params['account_id'] = (int) $filters['account_id'];
        }

        if (!empty($filters['category_id'])) {
            $clauses[] = 't.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['type'])) {
            $clauses[] = 't.transaction_type = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $clauses[] = 't.transaction_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 't.transaction_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (isset($filters['reviewed']) && $filters['reviewed'] !== '') {
            $clauses[] = 't.is_reviewed = :reviewed';
            $params['reviewed'] = $filters['reviewed'] === '1' ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $clauses[] = '(t.payee LIKE :search_payee OR t.notes LIKE :search_notes)';
            $params['search_payee'] = '%' . $filters['search'] . '%';
            $params['search_notes'] = '%' . $filters['search'] . '%';
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }
}
