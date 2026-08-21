<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class TransactionRepository
{
    private const PER_PAGE = 50;

    /**
     * Whitelist of columns the transactions list may be sorted by — never
     * built from user input directly, since this string is interpolated
     * into the ORDER BY clause (PDO can't parameterize a column name).
     */
    private const SORTABLE_COLUMNS = [
        'date' => 't.transaction_date',
        'payee' => 't.payee',
        'amount' => 't.amount',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $householdId, int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO transactions
                (household_id, account_id, category_id, is_split, created_by_user_id, last_edited_by_user_id,
                 transaction_type, transaction_date, amount, payee, notes,
                 exclude_from_budget, exclude_from_reports, created_at, updated_at)
             VALUES
                (:household_id, :account_id, :category_id, :is_split, :created_by_user_id, :last_edited_by_user_id,
                 :transaction_type, :transaction_date, :amount, :payee, :notes,
                 :exclude_from_budget, :exclude_from_reports, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'is_split' => !empty($data['is_split']) ? 1 : 0,
            'created_by_user_id' => $userId,
            'last_edited_by_user_id' => $userId,
            'transaction_type' => $data['transaction_type'],
            'transaction_date' => $data['transaction_date'],
            'amount' => $data['signed_amount'],
            'payee' => $data['payee'],
            'notes' => $data['notes'],
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

    /**
     * Links a transaction to the recurring item it was generated from,
     * mirroring how linkTransferPair() sets transfer_pair_id after the
     * fact — the transaction must already exist since this is always the
     * second step of a create-then-link sequence.
     */
    public function linkRecurringItem(int $transactionId, int $householdId, int $recurringItemId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET recurring_item_id = :recurring_item_id, updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'recurring_item_id' => $recurringItemId,
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
     * Every non-deleted, non-transfer transaction for the household —
     * used only for rule matching (live application against new
     * transactions reads one row at a time via findById(); this bulk read
     * backs the "preview and apply to existing transactions" flow, which
     * needs the full set to evaluate in PHP since matching logic isn't
     * expressible as SQL WHERE clauses). Transfers are excluded: their
     * payee/notes are system-generated ("Transfer to X") and rewriting
     * them via a rule would break the paired-transaction display.
     */
    public function listMatchableForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT * FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL AND transaction_type != 'transfer'
             ORDER BY id"
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }

    /**
     * Applies rule-derived overrides to a transaction. Deliberately
     * narrower than update(): only touches the fields a rule can actually
     * set, only writes fields that have a value (so multiple rules can
     * layer without one blanking out what another set), and is monotonic
     * for the boolean flag (a rule can turn exclude_from_reports on,
     * never back off).
     */
    public function applyRuleActions(int $transactionId, int $householdId, ?int $categoryId, ?string $payee, bool $excludeFromReports): void
    {
        $sets = ['updated_at = :updated_at'];
        $params = [
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ];

        if ($categoryId !== null) {
            $sets[] = 'category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($payee !== null) {
            $sets[] = 'payee = :payee';
            $params['payee'] = $payee;
        }

        if ($excludeFromReports) {
            $sets[] = 'exclude_from_reports = 1';
        }

        if (count($sets) === 1) {
            return;
        }

        $sql = 'UPDATE transactions SET ' . implode(', ', $sets) . ' WHERE id = :id AND household_id = :household_id';
        Connection::get()->prepare($sql)->execute($params);
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
                is_split = :is_split,
                last_edited_by_user_id = :last_edited_by_user_id,
                transaction_type = :transaction_type,
                transaction_date = :transaction_date,
                amount = :amount,
                payee = :payee,
                notes = :notes,
                exclude_from_budget = :exclude_from_budget,
                exclude_from_reports = :exclude_from_reports,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'is_split' => !empty($data['is_split']) ? 1 : 0,
            'last_edited_by_user_id' => $userId,
            'transaction_type' => $data['transaction_type'],
            'transaction_date' => $data['transaction_date'],
            'amount' => $data['signed_amount'],
            'payee' => $data['payee'],
            'notes' => $data['notes'],
            'exclude_from_budget' => $data['exclude_from_budget'] ? 1 : 0,
            'exclude_from_reports' => $data['exclude_from_reports'] ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $transactionId,
            'household_id' => $householdId,
        ]);
    }

    /**
     * Narrow update used for transfers, which only allow editing notes —
     * changing the amount or account on one side of a transfer without
     * touching the other would desync both balances.
     */
    public function updateNotes(int $transactionId, int $householdId, int $userId, ?string $notes): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE transactions SET
                notes = :notes,
                last_edited_by_user_id = :last_edited_by_user_id,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'notes' => $notes,
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
    public function listForHousehold(int $householdId, array $filters, int $page = 1, string $sort = 'date', string $dir = 'desc'): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        $countStmt = Connection::get()->prepare("SELECT COUNT(*) FROM transactions t {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? self::SORTABLE_COLUMNS['date'];
        $sortDir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT t.*, a.name AS account_name, c.name AS category_name, c.color AS category_color
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                ORDER BY {$sortColumn} {$sortDir}, t.id DESC
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
     * Income/expense/net totals across every row matching the current
     * filters (not just the current page) — powers the summary cards
     * above the transactions table. Transfers never count as income or
     * spending (CLAUDE.md's manual-only accounting rule), so they're
     * excluded from both sums even if the "All types" filter is active.
     *
     * @param array<string, mixed> $filters
     * @return array{income: string, expenses: string, net: string, incomeCount: int, expenseCount: int}
     */
    public function sumsForHousehold(int $householdId, array $filters): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);

        $stmt = Connection::get()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) AS expenses,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN 1 ELSE 0 END), 0) AS income_count,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN 1 ELSE 0 END), 0) AS expense_count
             FROM transactions t
             {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        $income = (string) $row['income'];
        $expenses = (string) $row['expenses'];

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => bcadd($income, $expenses, 2),
            'incomeCount' => (int) $row['income_count'],
            'expenseCount' => (int) $row['expense_count'],
        ];
    }

    /**
     * Count of income/expense transactions with no category assigned in
     * a date range — powers the dashboard's "N transactions need a
     * category" insight. Transfers are excluded: they legitimately have
     * no category, so counting them here would flag normal behavior as
     * something needing attention.
     */
    public function countUncategorized(int $householdId, string $dateFrom, string $dateTo): int
    {
        $stmt = Connection::get()->prepare(
            "SELECT COUNT(*) FROM transactions
             WHERE household_id = :household_id AND deleted_at IS NULL
               AND category_id IS NULL AND transaction_type != 'transfer'
               AND transaction_date >= :date_from AND transaction_date <= :date_to"
        );
        $stmt->execute([
            'household_id' => $householdId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return (int) $stmt->fetchColumn();
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

    public function listForRecurringItem(int $recurringItemId, int $householdId, int $limit = 10): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, a.name AS account_name
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.recurring_item_id = :recurring_item_id AND t.household_id = :household_id AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['recurring_item_id' => $recurringItemId, 'household_id' => $householdId]);

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
     * Bulk category assignment. Split transactions are silently excluded
     * from the WHERE clause (is_split = 0) — their category_id is a
     * derived "primary display" value (see
     * TransactionController::primaryCategoryFromSplits()), not something
     * a blanket bulk action should overwrite; the caller reports how many
     * rows were actually touched so it can tell the user if some were
     * skipped for that reason.
     *
     * @param list<int> $transactionIds
     * @return int rows actually updated
     */
    public function bulkSetCategory(array $transactionIds, int $householdId, int $categoryId): int
    {
        if ($transactionIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = Connection::get()->prepare(
            "UPDATE transactions SET category_id = ?, updated_at = ?
             WHERE household_id = ? AND is_split = 0 AND deleted_at IS NULL AND id IN ({$placeholders})"
        );
        $stmt->execute([$categoryId, gmdate('Y-m-d H:i:s'), $householdId, ...$transactionIds]);

        return $stmt->rowCount();
    }

    /**
     * Every non-deleted transaction among the given IDs that actually
     * belongs to this household — the authorization check for bulk
     * actions, since client-supplied IDs are never trusted on their own.
     *
     * @param list<int> $transactionIds
     * @return list<array>
     */
    public function findManyById(array $transactionIds, int $householdId): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT * FROM transactions WHERE household_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})"
        );
        $stmt->execute([$householdId, ...$transactionIds]);

        return $stmt->fetchAll();
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

        if (!empty($filters['search'])) {
            $clauses[] = '(t.payee LIKE :search_payee OR t.notes LIKE :search_notes)';
            $params['search_payee'] = '%' . $filters['search'] . '%';
            $params['search_notes'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['tag_id'])) {
            $clauses[] = 'EXISTS (SELECT 1 FROM transaction_tags tt WHERE tt.transaction_id = t.id AND tt.tag_id = :tag_id)';
            $params['tag_id'] = (int) $filters['tag_id'];
        }

        // Amount filters compare against the unsigned magnitude — a user
        // filtering "at least $100" means any transaction of $100 or more
        // regardless of whether it's income or an expense, not a raw
        // comparison against the signed stored value.
        if (!empty($filters['amount_min'])) {
            $clauses[] = 'ABS(t.amount) >= :amount_min';
            $params['amount_min'] = $filters['amount_min'];
        }

        if (!empty($filters['amount_max'])) {
            $clauses[] = 'ABS(t.amount) <= :amount_max';
            $params['amount_max'] = $filters['amount_max'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }
}
