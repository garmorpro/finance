<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AuditLogRepository
{
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
}
