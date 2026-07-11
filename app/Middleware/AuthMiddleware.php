<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
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
