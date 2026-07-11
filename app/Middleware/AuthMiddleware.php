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
}
