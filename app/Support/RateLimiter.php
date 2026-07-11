<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Connection;

final class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function tooManyAttempts(string $email, string $ipAddress): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0
               AND (email = :email OR ip_address = :ip_address)
               AND created_at >= :since'
        );

        $stmt->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
            'since' => gmdate('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60),
        ]);

        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function recordAttempt(string $email, string $ipAddress, bool $successful): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO login_attempts (email, ip_address, successful, created_at)
             VALUES (:email, :ip_address, :successful, :created_at)'
        );

        $stmt->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
            'successful' => $successful ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
