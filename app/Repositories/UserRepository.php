<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\DashboardWidgets;
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

    /**
     * `wide` is null when the user has never touched tile widths — the
     * caller falls back to each widget's own default width in that case.
     * Once saved, it's the complete authoritative set of wide tiles (same
     * "full list, not a diff" convention as `hidden`), including an
     * explicitly-saved empty array meaning "everything narrow."
     *
     * `statsOrder` and `mainOrder` are separate, independently-draggable
     * groups — see DashboardWidgets's class doc comment.
     *
     * @return array{statsOrder: list<string>, mainOrder: list<string>, hidden: list<string>, wide: list<string>|null}
     */
    public function getDashboardLayout(int $userId): array
    {
        $empty = ['statsOrder' => [], 'mainOrder' => [], 'hidden' => [], 'wide' => null];

        $stmt = Connection::get()->prepare('SELECT dashboard_layout FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            return $empty;
        }

        // A layout saved against an earlier widget set (widgets added,
        // removed, or renamed since) is discarded rather than partially
        // applied — otherwise known keys survive, new ones just get
        // appended to the end, and the page stops matching its own
        // current default layout. See DashboardWidgets::fingerprint().
        if (($decoded['widgetSetVersion'] ?? null) !== DashboardWidgets::fingerprint()) {
            return $empty;
        }

        $statsOrder = is_array($decoded['statsOrder'] ?? null) ? array_values(array_filter($decoded['statsOrder'], 'is_string')) : [];
        $mainOrder = is_array($decoded['mainOrder'] ?? null) ? array_values(array_filter($decoded['mainOrder'], 'is_string')) : [];
        $hidden = is_array($decoded['hidden'] ?? null) ? array_values(array_filter($decoded['hidden'], 'is_string')) : [];
        $wide = is_array($decoded['wide'] ?? null) ? array_values(array_filter($decoded['wide'], 'is_string')) : null;

        return ['statsOrder' => $statsOrder, 'mainOrder' => $mainOrder, 'hidden' => $hidden, 'wide' => $wide];
    }

    /**
     * @param list<string> $order
     */
    public function updateDashboardOrder(int $userId, string $group, array $order): void
    {
        $current = $this->getDashboardLayout($userId);
        $statsOrder = $group === 'stats' ? $order : $current['statsOrder'];
        $mainOrder = $group === 'main' ? $order : $current['mainOrder'];
        $this->saveDashboardLayout($userId, $statsOrder, $mainOrder, $current['hidden'], $current['wide']);
    }

    /**
     * @param list<string> $hidden
     */
    public function updateDashboardHidden(int $userId, array $hidden): void
    {
        $current = $this->getDashboardLayout($userId);
        $this->saveDashboardLayout($userId, $current['statsOrder'], $current['mainOrder'], $hidden, $current['wide']);
    }

    /**
     * @param list<string> $wide
     */
    public function updateDashboardWide(int $userId, array $wide): void
    {
        $current = $this->getDashboardLayout($userId);
        $this->saveDashboardLayout($userId, $current['statsOrder'], $current['mainOrder'], $current['hidden'], $wide);
    }

    /**
     * @param list<string> $statsOrder
     * @param list<string> $mainOrder
     * @param list<string> $hidden
     * @param list<string>|null $wide
     */
    private function saveDashboardLayout(int $userId, array $statsOrder, array $mainOrder, array $hidden, ?array $wide): void
    {
        $payload = [
            'statsOrder' => array_values($statsOrder),
            'mainOrder' => array_values($mainOrder),
            'hidden' => array_values($hidden),
            'widgetSetVersion' => DashboardWidgets::fingerprint(),
        ];
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
