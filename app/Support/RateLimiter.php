<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Connection;

final class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    // Registration is a heavier, harder-to-undo action than a login
    // attempt (it creates a real account and household), so it gets a
    // stricter allowance — fewer attempts, over a longer window — and
    // its own table (registration_attempts) rather than sharing
    // login_attempts, which is specifically about login outcomes.
    private const MAX_REGISTRATION_ATTEMPTS = 3;
    private const REGISTRATION_WINDOW_MINUTES = 60;

    // /quick-add/unlock is reachable with no login at all — a wrong
    // guess there isn't tied to any particular email the way a login
    // attempt is, so it gets its own IP-only table (same reasoning as
    // registration_attempts' own separation from login_attempts). The
    // key itself has 160 bits of entropy, so this is defense in depth
    // on top of that, not the only thing standing between a guesser and
    // a match — same allowance as login, since a wrong guess here is no
    // more informative to an attacker than a wrong password.
    private const MAX_QUICK_ADD_KEY_ATTEMPTS = 5;
    private const QUICK_ADD_KEY_WINDOW_MINUTES = 15;

    public static function tooManyRegistrationAttempts(string $ipAddress): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM registration_attempts
             WHERE ip_address = :ip_address AND created_at >= :since'
        );

        $stmt->execute([
            'ip_address' => $ipAddress,
            'since' => gmdate('Y-m-d H:i:s', time() - self::REGISTRATION_WINDOW_MINUTES * 60),
        ]);

        return (int) $stmt->fetchColumn() >= self::MAX_REGISTRATION_ATTEMPTS;
    }

    public static function recordRegistrationAttempt(string $ipAddress): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO registration_attempts (ip_address, created_at) VALUES (:ip_address, :created_at)'
        );

        $stmt->execute([
            'ip_address' => $ipAddress,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function tooManyQuickAddKeyAttempts(string $ipAddress): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM quick_add_key_attempts
             WHERE successful = 0 AND ip_address = :ip_address AND created_at >= :since'
        );

        $stmt->execute([
            'ip_address' => $ipAddress,
            'since' => gmdate('Y-m-d H:i:s', time() - self::QUICK_ADD_KEY_WINDOW_MINUTES * 60),
        ]);

        return (int) $stmt->fetchColumn() >= self::MAX_QUICK_ADD_KEY_ATTEMPTS;
    }

    public static function recordQuickAddKeyAttempt(string $ipAddress, bool $successful): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO quick_add_key_attempts (ip_address, successful, created_at) VALUES (:ip_address, :successful, :created_at)'
        );

        $stmt->execute([
            'ip_address' => $ipAddress,
            'successful' => $successful ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

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
