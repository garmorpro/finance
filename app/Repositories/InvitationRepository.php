<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class InvitationRepository
{
    public function create(
        int $householdId,
        int $invitedByUserId,
        string $email,
        string $role,
        string $tokenHash,
        string $expiresAt
    ): int {
        $stmt = Connection::get()->prepare(
            'INSERT INTO household_invitations
                (household_id, invited_by_user_id, email, role, token_hash, expires_at, created_at)
             VALUES
                (:household_id, :invited_by_user_id, :email, :role, :token_hash, :expires_at, :created_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'invited_by_user_id' => $invitedByUserId,
            'email' => $email,
            'role' => $role,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM household_invitations
             WHERE token_hash = :token_hash AND accepted_at IS NULL AND expires_at > :now
             LIMIT 1'
        );

        $stmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findPendingByHouseholdAndEmail(int $householdId, string $email): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM household_invitations
             WHERE household_id = :household_id AND email = :email
               AND accepted_at IS NULL AND expires_at > :now
             LIMIT 1'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'email' => $email,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function markAccepted(int $id): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE household_invitations SET accepted_at = :accepted_at WHERE id = :id'
        );

        $stmt->execute([
            'accepted_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function listPendingForHousehold(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT email, role, created_at, expires_at FROM household_invitations
             WHERE household_id = :household_id AND accepted_at IS NULL AND expires_at > :now
             ORDER BY created_at DESC'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }
}
