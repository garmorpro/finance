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
     * `wide` is null when the user has never touched tile widths — the
     * caller falls back to each widget's own default width in that case.
     * Once saved, it's the complete authoritative set of wide tiles (same
     * "full list, not a diff" convention as `hidden`), including an
     * explicitly-saved empty array meaning "everything narrow."
     *
     * @return array{order: list<string>, hidden: list<string>, wide: list<string>|null}
     */
    public function getDashboardLayout(int $userId): array
    {
        $stmt = Connection::get()->prepare('SELECT dashboard_layout FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            return ['order' => [], 'hidden' => [], 'wide' => null];
        }

        $order = is_array($decoded['order'] ?? null) ? array_values(array_filter($decoded['order'], 'is_string')) : [];
        $hidden = is_array($decoded['hidden'] ?? null) ? array_values(array_filter($decoded['hidden'], 'is_string')) : [];
        $wide = is_array($decoded['wide'] ?? null) ? array_values(array_filter($decoded['wide'], 'is_string')) : null;

        return ['order' => $order, 'hidden' => $hidden, 'wide' => $wide];
    }

    /**
     * @param list<string> $order
     */
    public function updateDashboardOrder(int $userId, array $order): void
    {
        $current = $this->getDashboardLayout($userId);
        $this->saveDashboardLayout($userId, $order, $current['hidden'], $current['wide']);
    }

    /**
     * @param list<string> $hidden
     */
    public function updateDashboardHidden(int $userId, array $hidden): void
    {
        $current = $this->getDashboardLayout($userId);
        $this->saveDashboardLayout($userId, $current['order'], $hidden, $current['wide']);
    }

    /**
     * @param list<string> $wide
     */
    public function updateDashboardWide(int $userId, array $wide): void
    {
        $current = $this->getDashboardLayout($userId);
        $this->saveDashboardLayout($userId, $current['order'], $current['hidden'], $wide);
    }

    /**
     * @param list<string> $order
     * @param list<string> $hidden
     * @param list<string>|null $wide
     */
    private function saveDashboardLayout(int $userId, array $order, array $hidden, ?array $wide): void
    {
        $payload = ['order' => array_values($order), 'hidden' => array_values($hidden)];
        if ($wide !== null) {
            $payload['wide'] = array_values($wide);
        }

        $stmt = Connection::get()->prepare(
            'UPDATE users SET dashboard_layout = :dashboard_layout, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            'dashboard_layout' => json_encode($payload),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }
}
