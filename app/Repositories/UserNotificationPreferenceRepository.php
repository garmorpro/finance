<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class UserNotificationPreferenceRepository
{
    /**
     * @return array<string, bool> keyed by notification_type — only types
     *     the user has explicitly toggled are present; callers should treat
     *     a missing key as "enabled" (opt-out default).
     */
    public function listForUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT notification_type, enabled FROM user_notification_preferences WHERE user_id = :user_id'
        );

        $stmt->execute(['user_id' => $userId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['notification_type']] = (bool) $row['enabled'];
        }

        return $result;
    }

    public function setEnabled(int $userId, string $type, bool $enabled): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO user_notification_preferences (user_id, notification_type, enabled, created_at, updated_at)
             VALUES (:user_id, :notification_type, :enabled, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE enabled = :enabled_update, updated_at = :updated_at_update'
        );

        $stmt->execute([
            'user_id' => $userId,
            'notification_type' => $type,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
            'enabled_update' => $enabled ? 1 : 0,
            'updated_at_update' => $now,
        ]);
    }
}
