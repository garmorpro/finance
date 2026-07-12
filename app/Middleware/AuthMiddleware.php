<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\UserSessionRepository;

final class AuthMiddleware
{
    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        return self::sessionIsValid();
    }

    /**
     * Checks this request's PHP session against user_sessions — the
     * revocation check behind Settings > Security's "log out this
     * device." A session with no tracked row at all (every session that
     * existed before this feature was added, or logins from any code
     * path that doesn't call recordSession()) is treated as valid and
     * backfilled here rather than force-logged-out — the alternative
     * would silently log out every already-signed-in user the moment
     * this migration runs. Once a row exists, an explicit revocation
     * (revoked_at set) does force a logout.
     */
    private static function sessionIsValid(): bool
    {
        $sessionId = session_id();
        if ($sessionId === '' || $sessionId === false) {
            return true;
        }

        $repo = new UserSessionRepository();
        $record = $repo->findBySessionId($sessionId);

        if ($record === null) {
            $repo->recordSession(
                (int) $_SESSION['user_id'],
                $sessionId,
                isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
            );

            return true;
        }

        if ($record['revoked_at'] !== null) {
            $_SESSION = [];
            session_destroy();

            return false;
        }

        // Throttled to roughly once a minute per session rather than
        // every single request, to keep this cheap.
        if (strtotime((string) $record['last_active_at']) < time() - 60) {
            $repo->touchLastActive($sessionId);
        }

        return true;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function householdId(): ?int
    {
        return isset($_SESSION['household_id']) ? (int) $_SESSION['household_id'] : null;
    }

    public static function role(): ?string
    {
        return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
    }

    public static function userName(): string
    {
        return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : '';
    }

    public static function userEmail(): string
    {
        return isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : '';
    }

    public static function setUserIdentity(string $name, string $email): void
    {
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
    }

    /**
     * @param string[] $roles
     */
    public static function requireRole(array $roles): void
    {
        self::requireAuth();

        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You do not have permission to do that.']);
            exit;
        }
    }
}
