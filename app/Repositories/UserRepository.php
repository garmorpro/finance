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
     * @return list<string>|null
     */
    public function getDashboardLayout(int $userId): ?array
    {
        $stmt = Connection::get()->prepare('SELECT dashboard_layout FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        $raw = $stmt->fetchColumn();

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : null;
    }

    /**
     * @param list<string> $order
     */
    public function updateDashboardLayout(int $userId, array $order): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE users SET dashboard_layout = :dashboard_layout, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'dashboard_layout' => json_encode(array_values($order)),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }
}
