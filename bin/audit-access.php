<?php

declare(strict_types=1);

use App\Database\Connection;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Read-only report of everyone and everything with access to this
 * deployment's database — households, users, active sessions, pending
 * invitations, and recent security-relevant audit log activity. Written
 * after a stranger reached the (now-closed — see App\Support\
 * PublicRegistration) public registration form, to answer "is there
 * anything in here that isn't mine?"
 *
 * Never writes anything. Nothing here decides what's suspicious on its
 * own — it just puts everything in front of you to make that call;
 * households.name/users.name/users.email are never encrypted at rest
 * (see docs/security.md's "Encryption at rest" section for exactly
 * which columns are), so what's printed here is exactly what's stored.
 *
 * Run with: php bin/audit-access.php
 */

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$pdo = Connection::get();

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function printRowsOrNone(array $rows, callable $formatter): void
{
    if ($rows === []) {
        echo "(none)\n";
        return;
    }

    foreach ($rows as $row) {
        echo $formatter($row) . "\n";
    }
}

section('Households');
printRowsOrNone(
    $pdo->query(
        "SELECT h.id, h.name, u.name AS owner_name, u.email AS owner_email, h.created_at,
                (SELECT COUNT(*) FROM household_members m WHERE m.household_id = h.id) AS member_count
         FROM households h
         JOIN users u ON u.id = h.owner_user_id
         WHERE h.deleted_at IS NULL
         ORDER BY h.created_at"
    )->fetchAll(),
    fn (array $r): string => "#{$r['id']}  \"{$r['name']}\"  owner: {$r['owner_name']} <{$r['owner_email']}>  "
        . "members: {$r['member_count']}  created: {$r['created_at']} UTC"
);

section('Users');
printRowsOrNone(
    $pdo->query(
        "SELECT u.id, u.name, u.email, u.is_active, u.email_verified_at, u.last_login_at, u.created_at,
                GROUP_CONCAT(DISTINCT h.name SEPARATOR ', ') AS households
         FROM users u
         LEFT JOIN household_members m ON m.user_id = u.id
         LEFT JOIN households h ON h.id = m.household_id AND h.deleted_at IS NULL
         WHERE u.deleted_at IS NULL
         GROUP BY u.id
         ORDER BY u.created_at"
    )->fetchAll(),
    function (array $r): string {
        $active = ((int) $r['is_active']) === 1 ? 'active' : 'INACTIVE';
        $verified = $r['email_verified_at'] !== null ? 'verified' : 'NOT VERIFIED';
        $households = $r['households'] !== null ? $r['households'] : '(none)';
        $lastLogin = $r['last_login_at'] ?? 'never';

        return "#{$r['id']}  {$r['name']} <{$r['email']}>  [{$active}, {$verified}]  "
            . "household(s): {$households}  last login: {$lastLogin}  created: {$r['created_at']} UTC";
    }
);

section('Registration activity (audit log)');
printRowsOrNone(
    $pdo->query(
        "SELECT created_at, action, ip_address, metadata
         FROM audit_logs
         WHERE action IN ('household.registered', 'registration.rate_limited', 'household.bootstrap')
         ORDER BY created_at DESC
         LIMIT 50"
    )->fetchAll(),
    fn (array $r): string => "{$r['created_at']} UTC  {$r['action']}  ip={$r['ip_address']}  {$r['metadata']}"
);

section('Recent failed logins / rate-limited attempts (last 200, any kind)');
printRowsOrNone(
    $pdo->query(
        "SELECT created_at, action, user_id, ip_address, metadata
         FROM audit_logs
         WHERE action IN (
             'login.failed', 'login.rate_limited', 'login.blocked_unverified_email',
             'login.webauthn_failed', 'quick_add_key.unlock_failed', 'quick_add_key.rate_limited'
         )
         ORDER BY created_at DESC
         LIMIT 200"
    )->fetchAll(),
    fn (array $r): string => "{$r['created_at']} UTC  {$r['action']}  user_id=" . ($r['user_id'] ?? '-')
        . "  ip={$r['ip_address']}  {$r['metadata']}"
);

section('Active sessions (not logged out/revoked)');
printRowsOrNone(
    $pdo->query(
        "SELECT s.id, u.name, u.email, s.ip_address, s.user_agent, s.created_at, s.last_active_at
         FROM user_sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.revoked_at IS NULL
         ORDER BY s.last_active_at DESC"
    )->fetchAll(),
    fn (array $r): string => "session #{$r['id']}  {$r['name']} <{$r['email']}>  ip={$r['ip_address']}  "
        . "last active: {$r['last_active_at']} UTC  started: {$r['created_at']} UTC  ({$r['user_agent']})"
);

section('Pending household invitations (unaccepted, unexpired)');
printRowsOrNone(
    $pdo->query(
        "SELECT i.id, h.name AS household_name, i.email, i.role, i.expires_at, i.created_at
         FROM household_invitations i
         JOIN households h ON h.id = i.household_id
         WHERE i.accepted_at IS NULL AND i.expires_at > UTC_TIMESTAMP()
         ORDER BY i.created_at DESC"
    )->fetchAll(),
    fn (array $r): string => "invite #{$r['id']}  to {$r['email']} ({$r['role']}) for \"{$r['household_name']}\"  "
        . "expires: {$r['expires_at']} UTC"
);

section('Registered passkeys / hardware keys');
printRowsOrNone(
    $pdo->query(
        "SELECT w.id, u.name, u.email, w.device_name, w.created_at, w.last_used_at
         FROM webauthn_credentials w
         JOIN users u ON u.id = w.user_id
         ORDER BY w.created_at DESC"
    )->fetchAll(),
    fn (array $r): string => "passkey #{$r['id']}  {$r['name']} <{$r['email']}>  \"{$r['device_name']}\"  "
        . "created: {$r['created_at']} UTC  last used: " . ($r['last_used_at'] ?? 'never') . " UTC"
);

section('Row count vs. highest ID ever assigned (reveals past deletions)');
foreach (['households', 'users'] as $table) {
    $autoIncrement = (int) $pdo->query(
        "SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'"
    )->fetchColumn();
    $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    // AUTO_INCREMENT is the *next* id to be handed out, so minus one is
    // the highest id this table has ever assigned, deleted or not — a
    // gap between that and how many rows actually exist now is direct
    // proof something was created and later removed, independent of
    // whatever an app-level "deleted_at" soft-delete column says.
    $highestEverAssigned = $autoIncrement - 1;
    $everRemoved = $highestEverAssigned - $rowCount;

    echo "{$table}: {$rowCount} row(s) now, highest id ever assigned was {$highestEverAssigned}";
    echo $everRemoved > 0 ? " — {$everRemoved} row(s) existed at some point and are now gone\n" : " — none ever removed\n";
}

section('Integrity check');
$usersWithoutHousehold = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN household_members m ON m.user_id = u.id
     WHERE m.id IS NULL AND u.deleted_at IS NULL"
)->fetchColumn();
echo "Active users not in any household: {$usersWithoutHousehold}\n";

/**
 * Every (table, column, referenced table) triple this schema's own
 * migrations declare a real FOREIGN KEY for, pointed at households or
 * users — see `grep -rhoE "FOREIGN KEY \([a-z_]+\) REFERENCES
 * (households|users)"  database/migrations/*.sql` for how this list was
 * built. A live FK constraint makes a genuine dangling reference
 * impossible through a normal DELETE (MySQL refuses it outright) — the
 * only way one of these can actually be non-zero is if
 * FOREIGN_KEY_CHECKS was turned off for a manual cleanup (a common
 * instinct when a delete hits a constraint error), which is exactly the
 * scenario worth checking for after removing something by hand rather
 * than through the app itself.
 */
$foreignKeyChecks = [
    ['accounts', 'household_id', 'households'],
    ['accounts', 'created_by_user_id', 'users'],
    ['account_balance_history', 'changed_by_user_id', 'users'],
    ['attachments', 'uploaded_by_user_id', 'users'],
    ['audit_logs', 'household_id', 'households'],
    ['audit_logs', 'user_id', 'users'],
    ['budget_category_defaults', 'household_id', 'households'],
    ['budget_review_links', 'household_id', 'households'],
    ['budget_review_links', 'user_id', 'users'],
    ['budgets', 'household_id', 'households'],
    ['categories', 'household_id', 'households'],
    ['category_groups', 'household_id', 'households'],
    ['email_verification_tokens', 'user_id', 'users'],
    ['financial_goals', 'household_id', 'households'],
    ['financial_goals', 'created_by_user_id', 'users'],
    ['financial_goals', 'responsible_user_id', 'users'],
    ['goal_contributions', 'user_id', 'users'],
    ['household_invitations', 'household_id', 'households'],
    ['household_invitations', 'invited_by_user_id', 'users'],
    ['household_members', 'household_id', 'households'],
    ['household_members', 'user_id', 'users'],
    ['households', 'owner_user_id', 'users'],
    ['imports', 'household_id', 'households'],
    ['imports', 'imported_by_user_id', 'users'],
    ['password_reset_tokens', 'user_id', 'users'],
    ['recurring_items', 'household_id', 'households'],
    ['recurring_items', 'created_by_user_id', 'users'],
    ['tags', 'household_id', 'households'],
    ['transaction_rules', 'household_id', 'households'],
    ['transaction_rules', 'created_by_user_id', 'users'],
    ['transactions', 'household_id', 'households'],
    ['transactions', 'created_by_user_id', 'users'],
    ['transactions', 'last_edited_by_user_id', 'users'],
    ['user_notification_preferences', 'user_id', 'users'],
    ['user_sessions', 'user_id', 'users'],
    ['webauthn_credentials', 'user_id', 'users'],
];

$danglingFound = false;
foreach ($foreignKeyChecks as [$table, $column, $referencedTable]) {
    $count = (int) $pdo->query(
        "SELECT COUNT(*) FROM `{$table}` c
         LEFT JOIN `{$referencedTable}` p ON p.id = c.`{$column}`
         WHERE c.`{$column}` IS NOT NULL AND p.id IS NULL"
    )->fetchColumn();

    if ($count > 0) {
        $danglingFound = true;
        echo "DANGLING: {$table}.{$column} has {$count} row(s) pointing at a {$referencedTable} row that no longer exists\n";
    }
}

if (!$danglingFound) {
    echo "No dangling references found across " . count($foreignKeyChecks) . " household_id/user_id foreign keys checked.\n";
}

echo "\nDone. Everything above is exactly what's in the database — nothing here decides\n"
    . "what's yours. If a household, user, session, invitation, or DANGLING line above\n"
    . "isn't something you recognize or expect, don't delete anything else yet — flag it\n"
    . "and we'll figure out the safest way to clean it up.\n";
