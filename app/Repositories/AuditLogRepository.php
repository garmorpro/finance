<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AuditLogRepository
{
    private const PER_PAGE = 50;

    public function log(
        ?int $userId,
        ?int $householdId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $ipAddress = null,
        array $metadata = []
    ): void {
        $stmt = Connection::get()->prepare(
            'INSERT INTO audit_logs (user_id, household_id, action, entity_type, entity_id, ip_address, metadata, created_at)
             VALUES (:user_id, :household_id, :action, :entity_type, :entity_id, :ip_address, :metadata, :created_at)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'household_id' => $householdId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $ipAddress,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Settings > Audit Log (Owner only — see AuditLogController). A row
     * belongs to this household's view either because it's directly
     * tagged with this household_id, or because it's tied to a user who
     * is currently a member here — several security events (a failed
     * login, a failed 2FA code) are logged with household_id left NULL,
     * since session/household context doesn't exist yet at that moment,
     * but they're still exactly the kind of thing this page exists to
     * surface for that user's household. A row with neither a household
     * nor a recognizable member (e.g. a failed Quick Add key guess from
     * a stranger, or a failed login against an email with no account at
     * all) isn't attributable to any specific household and is
     * correctly excluded — bin/audit-access.php is the tool for that
     * broader, whole-server view.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array{rows: array, total: int, page: int, perPage: int}
     */
    public function listForHousehold(int $householdId, array $filters, int $page = 1): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        [$where, $params] = $this->buildWhere($householdId, $filters);

        $countStmt = Connection::get()->prepare("SELECT COUNT(*) FROM audit_logs a {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                {$where}
                ORDER BY a.created_at DESC, a.id DESC
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
     * @param array{date_from?: string, date_to?: string, category?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(int $householdId, array $filters): array
    {
        $clauses = ['(a.household_id = :household_id OR a.user_id IN (SELECT user_id FROM household_members WHERE household_id = :household_id_members))'];
        $params = ['household_id' => $householdId, 'household_id_members' => $householdId];

        if (!empty($filters['date_from'])) {
            $clauses[] = 'a.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 'a.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        // Mirrors App\Support\AuditActionLabels::category() as a SQL
        // fragment rather than sharing code with it — that method
        // classifies a single already-known action string for display,
        // this builds a WHERE clause, different enough jobs that a
        // shared implementation would need to serve both awkwardly. Keep
        // the two in sync if a new category-affecting action pattern is
        // ever added.
        if (($filters['category'] ?? '') === 'quick_add_key') {
            $clauses[] = "(a.action LIKE 'quick\\_add\\_key.%' OR a.action = 'transaction.created_via_quick_add_key')";
        } elseif (($filters['category'] ?? '') === 'security') {
            $clauses[] = "(a.action LIKE 'login.%' OR a.action LIKE '2fa.%' OR a.action LIKE 'webauthn.%'
                OR a.action LIKE 'password.%' OR a.action LIKE 'password\\_reset.%' OR a.action LIKE 'session.%'
                OR a.action LIKE 'registration.%' OR a.action IN ('email.verified', 'logout'))";
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }
}
