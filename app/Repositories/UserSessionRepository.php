<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * Tracks PHP sessions in the database so an individual login can be
 * revoked from another session — PHP's native file-based session storage
 * has no such concept on its own. AuthMiddleware checks this table on
 * every authenticated request; see its doc comment for the "untracked
 * session" self-heal behavior that avoids a disruptive mass logout the
 * first time this table exists.
 */
final class UserSessionRepository
{
    /**
     * Inserts a new tracked session, or — if this exact PHP session ID
     * somehow already has a row (re-login without a fresh ID, or the
     * self-heal path in AuthMiddleware) — revives it as active rather
     * than erroring on the unique constraint.
     */
    public function recordSession(int $userId, string $sessionId, ?string $ipAddress, ?string $userAgent): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 255) : null;

        $stmt = Connection::get()->prepare(
            'INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active_at)
             VALUES (:user_id, :session_id, :ip_address, :user_agent, :created_at, :last_active_at)
             ON DUPLICATE KEY UPDATE
                user_id = :user_id_update,
                ip_address = :ip_address_update,
                user_agent = :user_agent_update,
                last_active_at = :last_active_at_update,
                revoked_at = NULL'
        );

        $stmt->execute([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => $now,
            'last_active_at' => $now,
            'user_id_update' => $userId,
            'ip_address_update' => $ipAddress,
            'user_agent_update' => $userAgent,
            'last_active_at_update' => $now,
        ]);
    }

    public function findBySessionId(string $sessionId): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM user_sessions WHERE session_id = :session_id LIMIT 1');
        $stmt->execute(['session_id' => $sessionId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function touchLastActive(string $sessionId): void
    {
        $stmt = Connection::get()->prepare('UPDATE user_sessions SET last_active_at = :now WHERE session_id = :session_id');
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'session_id' => $sessionId]);
    }

    /**
     * @return list<array>
     */
    public function listActiveForUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM user_sessions WHERE user_id = :user_id AND revoked_at IS NULL ORDER BY last_active_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Scoped to the given user — the authorization check for "log out
     * this session," since a session's row ID alone is guessable/
     * sequential like any other.
     */
    public function revoke(int $sessionRowId, int $userId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE user_sessions SET revoked_at = :now WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $sessionRowId, 'user_id' => $userId]);
    }

    public function revokeBySessionId(string $sessionId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE user_sessions SET revoked_at = :now WHERE session_id = :session_id'
        );
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'session_id' => $sessionId]);
    }

    /**
     * "Log out everywhere else" — every other active session for this
     * user, leaving the one currently in use untouched.
     *
     * @return int number of sessions revoked
     */
    public function revokeAllExcept(int $userId, string $currentSessionId): int
    {
        $stmt = Connection::get()->prepare(
            'UPDATE user_sessions SET revoked_at = :now
             WHERE user_id = :user_id AND session_id != :session_id AND revoked_at IS NULL'
        );
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId, 'session_id' => $currentSessionId]);

        return $stmt->rowCount();
    }
}
