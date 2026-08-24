<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
             VALUES (:name, :email, :password_hash, 1, :created_at, :updated_at)'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    /**
     * Set once — at public-registration verification time
     * (RegistrationController::verifyEmail()), or immediately at account
     * creation for paths that already prove email ownership another way
     * (accepting a household invitation email, bin/create-owner.php).
     */
    public function markEmailVerified(int $userId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET email_verified_at = :verified_at, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'verified_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    public function updateLastLogin(int $userId): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'last_login_at' => $now,
            'updated_at' => $now,
            'id' => $userId,
        ]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'password_hash' => $passwordHash,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    public function updateProfile(int $userId, string $name, string $email): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET name = :name, email = :email, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    /**
     * @param list<string> $recoveryCodeHashes already password_hash()'d —
     *     recovery codes are stored the same way passwords are, never
     *     in plaintext.
     */
    public function enableTwoFactor(int $userId, string $secret, array $recoveryCodeHashes): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET two_factor_secret = :secret, two_factor_enabled_at = :enabled_at,
                two_factor_recovery_codes = :codes, updated_at = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            'secret' => $secret,
            'enabled_at' => gmdate('Y-m-d H:i:s'),
            'codes' => json_encode(array_values($recoveryCodeHashes)),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    public function disableTwoFactor(int $userId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET two_factor_secret = NULL, two_factor_enabled_at = NULL,
                two_factor_recovery_codes = NULL, updated_at = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    /**
     * Checks a submitted recovery code against the stored (hashed) set
     * and, if it matches, removes it so it can't be used a second time.
     * Returns false without side effects on no match.
     */
    public function consumeRecoveryCode(int $userId, string $code): bool
    {
        $user = $this->findById($userId);
        if ($user === null || $user['two_factor_recovery_codes'] === null) {
            return false;
        }

        $hashes = json_decode($user['two_factor_recovery_codes'], true);
        if (!is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($hashes[$index]);

                $stmt = Connection::get()->prepare(
                    'UPDATE users SET two_factor_recovery_codes = :codes, updated_at = :updated_at WHERE id = :id'
                );
                $stmt->execute([
                    'codes' => json_encode(array_values($hashes)),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                    'id' => $userId,
                ]);

                return true;
            }
        }

        return false;
    }
}
