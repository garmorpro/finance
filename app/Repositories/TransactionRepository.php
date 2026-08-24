<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\FieldCipher;

final class TransactionRepository
{
    private const PER_PAGE = 50;

    /**
     * Whitelist of columns the SQL fast path may sort by — never built from
     * user input directly, since this string is interpolated into the
     * ORDER BY clause (PDO can't parameterize a column name). 'payee' and
     * 'amount' are no longer reachable here: now that both columns are
     * encrypted (see App\Support\FieldCipher), sorting by either one is
     * handled entirely in PHP by listForHouseholdViaPhp() instead — see
     * listForHousehold()'s routing.
     */
    private const SORTABLE_COLUMNS = [
        'date' => 't.transaction_date',
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
                 transaction_type, transaction_date, amount, amount_encrypted, payee, payee_encrypted,
                 notes, notes_encrypted, exclude_from_budget, exclude_from_reports, created_at, updated_at)
             VALUES
                (:household_id, :account_id, :category_id, :is_split, :created_by_user_id, :last_edited_by_user_id,
                 :transaction_type, :transaction_date, NULL, :amount_encrypted, NULL, :payee_encrypted,
                 NULL, :notes_encrypted, :exclude_from_budget, :exclude_from_reports, :created_at, :updated_at)'
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
            'amount_encrypted' => FieldCipher::encrypt($data['signed_amount']),
            'payee_encrypted' => FieldCipher::encrypt($data['payee']),
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
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

        return $row === false ? null : self::hydrate($row);
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

        return array_map(self::hydrate(...), $stmt->fetchAll());
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
            $sets[] = 'payee = NULL';
            $sets[] = 'payee_encrypted = :payee_encrypted';
            $params['payee_encrypted'] = FieldCipher::encrypt($payee);
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
     *
     * amount/payee are encrypted (see App\Support\FieldCipher), so an exact
     * SQL equality match against them is no longer possible — the same
     * plaintext never produces the same ciphertext bytes twice, since each
     * encryption uses a fresh random nonce. Narrowed to candidates by
     * household/account/date first (all unencrypted, still a real SQL
     * filter — typically a handful of rows for one account on one day),
     * then compared in PHP after decrypting each candidate.
     */
    public function existsSimilar(int $householdId, int $accountId, string $date, string $signedAmount, string $payee): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT amount, amount_encrypted, payee, payee_encrypted FROM transactions
             WHERE household_id = :household_id AND account_id = :account_id
               AND transaction_date = :date AND deleted_at IS NULL'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'account_id' => $accountId,
            'date' => $date,
        ]);

        foreach ($stmt->fetchAll() as $row) {
            $rowAmount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);
            $rowPayee = FieldCipher::decryptOrFallback($row['payee_encrypted'], $row['payee']);

            if ($rowAmount !== null && bccomp($rowAmount, $signedAmount, 2) === 0 && $rowPayee === $payee) {
                return true;
            }
        }

        return false;
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
                amount = NULL,
                amount_encrypted = :amount_encrypted,
                payee = NULL,
                payee_encrypted = :payee_encrypted,
                notes = NULL,
                notes_encrypted = :notes_encrypted,
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
            'amount_encrypted' => FieldCipher::encrypt($data['signed_amount']),
            'payee_encrypted' => FieldCipher::encrypt($data['payee']),
            'notes_encrypted' => FieldCipher::encrypt($data['notes']),
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
                notes = NULL,
                notes_encrypted = :notes_encrypted,
                last_edited_by_user_id = :last_edited_by_user_id,
                updated_at = :updated_at
             WHERE id = :id AND household_id = :household_id'
        );

        $stmt->execute([
            'notes_encrypted' => FieldCipher::encrypt($notes),
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
        $page = max(1, $page);

        // amount/payee are encrypted (see App\Support\FieldCipher), so an
        // amount-range filter, a payee/notes search, or a sort by amount/
        // payee can't be expressed as SQL against them — all three have to
        // decrypt every SQL-filterable candidate row in PHP first. Every
        // other filter (date, account, category, type, tag) stays a plain
        // indexed SQL query with SQL-level LIMIT/OFFSET, same as before.
        if (!empty($filters['search']) || !empty($filters['amount_min']) || !empty($filters['amount_max'])
            || in_array($sort, ['amount', 'payee'], true)
        ) {
            return $this->listForHouseholdViaPhp($householdId, $filters, $page, $sort, $dir);
        }

        return $this->listForHouseholdViaSql($householdId, $filters, $page, $sort, $dir);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: array, total: int, page: int, perPage: int}
     */
    private function listForHouseholdViaSql(int $householdId, array $filters, int $page, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);
        $offset = ($page - 1) * self::PER_PAGE;

        $countStmt = Connection::get()->prepare("SELECT COUNT(*) FROM transactions t {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? self::SORTABLE_COLUMNS['date'];
        $sortDir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT t.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name, c.color AS category_color
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                ORDER BY {$sortColumn} {$sortDir}, t.id DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return [
            'rows' => self::hydrateAccountName(array_map(self::hydrate(...), $stmt->fetchAll())),
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
        ];
    }

    /**
     * Slow path for an amount-range filter, a payee/notes search, or an
     * amount/payee sort — every row matching the SQL-safe filters
     * (date/account/category/type/tag) is fetched and decrypted in PHP,
     * capped at the same 20000-row ceiling exportForHousehold() uses so
     * this can't be made to pull an unbounded result set. Fine for a
     * self-hosted household's own transaction history; not a query shape
     * that would scale to a multi-tenant SaaS volume.
     *
     * @param array<string, mixed> $filters
     * @return array{rows: array, total: int, page: int, perPage: int}
     */
    private function listForHouseholdViaPhp(int $householdId, array $filters, int $page, string $sort, string $dir): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);

        $sql = "SELECT t.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name, c.color AS category_color
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                LIMIT 20000";

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        $rows = self::hydrateAccountName(array_map(self::hydrate(...), $stmt->fetchAll()));

        if (!empty($filters['amount_min']) || !empty($filters['amount_max'])) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => self::matchesAmountRange($row['amount'], $filters)
            ));
        }

        if (!empty($filters['search'])) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => self::matchesSearch($row, (string) $filters['search'])
            ));
        }

        $sortColumn = in_array($sort, ['amount', 'payee'], true) ? $sort : 'transaction_date';
        $sortDir = strtolower($dir) === 'asc' ? 1 : -1;

        usort($rows, static function (array $a, array $b) use ($sortColumn, $sortDir): int {
            if ($sortColumn === 'amount') {
                return $sortDir * bccomp($a['amount'], $b['amount'], 2);
            }

            if ($sortColumn === 'payee') {
                return $sortDir * strcmp((string) $a['payee'], (string) $b['payee']);
            }

            // Same "date, then id" tiebreak as the SQL path's
            // "ORDER BY ... , t.id DESC" so pagination stays stable.
            $cmp = $a['transaction_date'] <=> $b['transaction_date'];

            return $cmp !== 0 ? $sortDir * $cmp : $b['id'] <=> $a['id'];
        });

        $total = count($rows);
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'rows' => array_slice($rows, $offset, self::PER_PAGE),
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
     * amount is encrypted (see App\Support\FieldCipher), so this can no
     * longer SUM() in SQL — every matching row is fetched and decrypted,
     * then summed with bcadd, replicating the original SUM(CASE ...)
     * split exactly.
     *
     * @param array<string, mixed> $filters
     * @return array{income: string, expenses: string, net: string, incomeCount: int, expenseCount: int}
     */
    public function sumsForHousehold(int $householdId, array $filters): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);

        $stmt = Connection::get()->prepare(
            "SELECT t.transaction_type, t.amount, t.amount_encrypted, t.payee, t.payee_encrypted, t.notes, t.notes_encrypted
             FROM transactions t {$where}"
        );
        $stmt->execute($params);

        $income = '0.00';
        $expenses = '0.00';
        $incomeCount = 0;
        $expenseCount = 0;
        $search = (string) ($filters['search'] ?? '');

        foreach ($stmt->fetchAll() as $row) {
            $amount = FieldCipher::decryptOrFallback($row['amount_encrypted'], $row['amount']);

            if (!self::matchesAmountRange($amount, $filters)) {
                continue;
            }

            if ($search !== '' && !self::matchesSearch([
                'payee' => FieldCipher::decryptOrFallback($row['payee_encrypted'], $row['payee']),
                'notes' => FieldCipher::decryptOrFallback($row['notes_encrypted'], $row['notes']),
            ], $search)) {
                continue;
            }

            if ($row['transaction_type'] === 'income') {
                $income = bcadd($income, $amount, 2);
                $incomeCount++;
            } elseif ($row['transaction_type'] === 'expense') {
                $expenses = bcadd($expenses, $amount, 2);
                $expenseCount++;
            }
        }

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => bcadd($income, $expenses, 2),
            'incomeCount' => $incomeCount,
            'expenseCount' => $expenseCount,
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
     * be used to pull an unbounded result set. amount_min/amount_max and
     * search are applied in PHP after decrypting, same reasoning as
     * listForHouseholdViaPhp() — buildWhere() no longer emits SQL for
     * either of them.
     *
     * @param array<string, mixed> $filters
     */
    public function exportForHousehold(int $householdId, array $filters): array
    {
        [$where, $params] = $this->buildWhere($householdId, $filters);

        $sql = "SELECT t.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                {$where}
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT 20000";

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        $rows = self::hydrateAccountName(array_map(self::hydrate(...), $stmt->fetchAll()));

        if (!empty($filters['amount_min']) || !empty($filters['amount_max'])) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => self::matchesAmountRange($row['amount'], $filters)
            ));
        }

        if (!empty($filters['search'])) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => self::matchesSearch($row, (string) $filters['search'])
            ));
        }

        return $rows;
    }

    public function listForRecurringItem(int $recurringItemId, int $householdId, int $limit = 10): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.recurring_item_id = :recurring_item_id AND t.household_id = :household_id AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['recurring_item_id' => $recurringItemId, 'household_id' => $householdId]);

        return self::hydrateAccountName(array_map(self::hydrate(...), $stmt->fetchAll()));
    }

    public function recentForHousehold(int $householdId, int $limit = 5): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT t.*, a.name AS account_name, a.name_encrypted AS account_name_encrypted, c.name AS category_name, c.color AS category_color
             FROM transactions t
             INNER JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.household_id = :household_id AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT ' . max(1, $limit)
        );

        $stmt->execute(['household_id' => $householdId]);

        return self::hydrateAccountName(array_map(self::hydrate(...), $stmt->fetchAll()));
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

        return array_map(self::hydrate(...), $stmt->fetchAll());
    }

    /**
     * amount/payee/notes are encrypted (see App\Support\FieldCipher) —
     * every SELECT in this file that fetches full transaction rows (t.*)
     * runs them through this to decrypt into the same amount/payee/notes
     * keys every caller already expects, falling back to the legacy
     * plaintext column for any row not yet backfilled. Not used for the
     * narrower, hand-picked column lists in sumsForHousehold() and
     * existsSimilar(), which decrypt inline instead since they don't
     * select every column this expects.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $row['amount'] = FieldCipher::decryptOrFallback($row['amount_encrypted'] ?? null, $row['amount']);
        $row['payee'] = FieldCipher::decryptOrFallback($row['payee_encrypted'] ?? null, $row['payee']);
        $row['notes'] = FieldCipher::decryptOrFallback($row['notes_encrypted'] ?? null, $row['notes']);

        unset($row['amount_encrypted'], $row['payee_encrypted'], $row['notes_encrypted']);

        return $row;
    }

    /**
     * Amount filters compare against the unsigned magnitude — a user
     * filtering "at least $100" means any transaction of $100 or more
     * regardless of whether it's income or an expense, not a raw
     * comparison against the signed stored value. Applied in PHP because
     * amount is encrypted — see listForHouseholdViaPhp()'s doc comment.
     *
     * @param array<string, mixed> $filters
     */
    private static function matchesAmountRange(?string $amount, array $filters): bool
    {
        if ($amount === null) {
            return false;
        }

        $absAmount = bccomp($amount, '0.00', 2) < 0 ? bcmul($amount, '-1', 2) : $amount;

        if (!empty($filters['amount_min']) && bccomp($absAmount, (string) $filters['amount_min'], 2) < 0) {
            return false;
        }

        if (!empty($filters['amount_max']) && bccomp($absAmount, (string) $filters['amount_max'], 2) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Case-insensitive substring match against payee or notes — the PHP
     * replacement for the old "payee LIKE :search OR notes LIKE :search"
     * SQL clause, no longer possible once both columns are encrypted.
     *
     * @param array<string, mixed> $row
     */
    private static function matchesSearch(array $row, string $search): bool
    {
        $needle = mb_strtolower($search);
        $payee = mb_strtolower((string) ($row['payee'] ?? ''));
        $notes = mb_strtolower((string) ($row['notes'] ?? ''));

        return str_contains($payee, $needle) || str_contains($notes, $needle);
    }

    /**
     * account_name comes from a SQL JOIN against accounts.name, which
     * (see AccountRepository) is only ever NULL now — the real value
     * lives encrypted in accounts.name_encrypted, which every SELECT in
     * this file that joins accounts also selects alongside it as
     * account_name_encrypted. This decrypts it into the same
     * account_name key every caller already expects, so nothing calling
     * these methods needs to change, and removes the raw ciphertext
     * column from the row entirely.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function hydrateAccountName(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['account_name'] = FieldCipher::decryptOrFallback($row['account_name_encrypted'] ?? null, $row['account_name']);
            unset($row['account_name_encrypted']);
        }
        unset($row);

        return $rows;
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

        if (!empty($filters['tag_id'])) {
            $clauses[] = 'EXISTS (SELECT 1 FROM transaction_tags tt WHERE tt.transaction_id = t.id AND tt.tag_id = :tag_id)';
            $params['tag_id'] = (int) $filters['tag_id'];
        }

        // search/amount_min/amount_max deliberately have no SQL clause
        // here — payee/notes/amount are encrypted (see
        // App\Support\FieldCipher), so neither a LIKE search nor a numeric
        // range comparison can run against the stored ciphertext. Every
        // caller of buildWhere() applies those two filters itself in PHP
        // after decrypting, via matchesSearch()/matchesAmountRange().

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }
}
