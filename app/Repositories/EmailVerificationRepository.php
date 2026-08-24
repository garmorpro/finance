<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class EmailVerificationRepository
{
    public function create(int $userId, string $tokenHash, string $expiresAt): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM email_verification_tokens
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now
             LIMIT 1'
        );

        $stmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function markUsed(int $id): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE email_verification_tokens SET used_at = :used_at WHERE id = :id'
        );

        $stmt->execute([
            'used_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function invalidateAllForUser(int $userId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE email_verification_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL'
        );

        $stmt->execute([
            'used_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }
}
