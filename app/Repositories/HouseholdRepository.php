<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class HouseholdRepository
{
    public function create(string $name, int $ownerUserId): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO households (name, owner_user_id, created_at, updated_at)
             VALUES (:name, :owner_user_id, :created_at, :updated_at)'
        );

        $stmt->execute([
            'name' => $name,
            'owner_user_id' => $ownerUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function addMember(int $householdId, int $userId, string $role): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = Connection::get()->prepare(
            'INSERT INTO household_members (household_id, user_id, role, created_at, updated_at)
             VALUES (:household_id, :user_id, :role, :created_at, :updated_at)'
        );

        $stmt->execute([
            'household_id' => $householdId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findMembership(int $userId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT household_id, role FROM household_members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);

        $membership = $stmt->fetch();

        return $membership === false ? null : $membership;
    }

    public function findById(int $householdId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM households WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $householdId]);

        $household = $stmt->fetch();

        return $household === false ? null : $household;
    }

    public function updateBudgetReminderSettings(
        int $householdId,
        bool $enabled,
        int $daysBefore,
        bool $linkSingleUse,
        int $linkExpiryDays
    ): void {
        $stmt = Connection::get()->prepare(
            'UPDATE households
             SET budget_reminder_enabled = :enabled,
                 budget_reminder_days_before = :days_before,
                 budget_reminder_link_single_use = :link_single_use,
                 budget_reminder_link_expiry_days = :link_expiry_days,
                 updated_at = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            'enabled' => $enabled ? 1 : 0,
            'days_before' => $daysBefore,
            'link_single_use' => $linkSingleUse ? 1 : 0,
            'link_expiry_days' => $linkExpiryDays,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $householdId,
        ]);
    }

    /**
     * Every household with the budget planning reminder turned on — used
     * by bin/send-budget-reminders.php, which still checks each one's own
     * days_before window before actually issuing anything.
     */
    public function listWithBudgetReminderEnabled(): array
    {
        $stmt = Connection::get()->query(
            'SELECT * FROM households WHERE budget_reminder_enabled = 1 AND deleted_at IS NULL'
        );

        return $stmt->fetchAll();
    }

    public function listMembers(int $householdId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT users.id, users.name, users.email, household_members.role, household_members.created_at
             FROM household_members
             INNER JOIN users ON users.id = household_members.user_id
             WHERE household_members.household_id = :household_id AND users.deleted_at IS NULL
             ORDER BY FIELD(household_members.role, "owner", "administrator", "member", "viewer"), users.name'
        );

        $stmt->execute(['household_id' => $householdId]);

        return $stmt->fetchAll();
    }
}
