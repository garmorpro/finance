<?php

declare(strict_types=1);

use App\Database\Connection;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * One-time reset: empties every table except the structural/reference
 * ones a household needs to keep using the app immediately afterward
 * (accounts, categories/category_groups, households/household_members,
 * users, webauthn_credentials, plus the migrations tracking table).
 * Everything else — transactions, budgets, goals, recurring items,
 * imports, rules, tags, sessions, audit logs, balance history — is
 * wiped via TRUNCATE (resets auto-increment too, not just rows).
 *
 * FOREIGN_KEY_CHECKS is disabled for the duration of --apply: several
 * wiped tables reference each other (budget_items -> budgets,
 * transaction_splits -> transactions, goal_contributions ->
 * financial_goals, etc.), and MySQL refuses to TRUNCATE any table a
 * foreign key still points at — even one about to be truncated itself
 * two lines later — unless checks are off. Kept tables (accounts,
 * categories, ...) are never touched, so nothing here risks their data
 * even with checks disabled; it only removes the restriction on
 * ordering *among the wiped tables*.
 *
 * Also refuses to run at all if the database has a table this script
 * doesn't know about (schema drift since this was written) — silently
 * keeping or silently wiping something nobody explicitly decided about
 * is worse than stopping and asking.
 *
 * Defaults to a dry run — prints row counts per table without deleting
 * anything. Pass --apply to actually wipe. BACK UP YOUR DATABASE FIRST
 * (mysqldump) — this cannot be undone from within the app.
 *   php bin/wipe-transactional-data.php
 *   php bin/wipe-transactional-data.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$pdo = Connection::get();

$keep = [
    'accounts',
    'categories',
    'category_groups',
    'households',
    'household_members',
    'migrations',
    'users',
    'webauthn_credentials',
];

$wipe = [
    'account_balance_history',
    'attachments',
    'audit_logs',
    'budget_category_defaults',
    'budget_items',
    'budgets',
    'financial_goals',
    'goal_contributions',
    'household_invitations',
    'import_rows',
    'imports',
    'login_attempts',
    'password_reset_tokens',
    'recurring_items',
    'tags',
    'transaction_rule_actions',
    'transaction_rule_conditions',
    'transaction_rules',
    'transaction_splits',
    'transaction_tags',
    'transactions',
    'user_notification_preferences',
    'user_sessions',
];

$actualTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$unaccounted = array_diff($actualTables, array_merge($keep, $wipe));

if ($unaccounted !== []) {
    echo "Stopping — found table(s) not in either the keep or wipe list: " . implode(', ', $unaccounted) . "\n";
    echo "Add each one to whichever list is correct in this script before running it.\n";
    exit(1);
}

echo $apply
    ? "Wiping data — FOREIGN_KEY_CHECKS disabled for this run.\n\n"
    : "Dry run — nothing will be deleted. Re-run with --apply to actually wipe.\n\n";

$totalRows = 0;

if ($apply) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
}

try {
    foreach ($wipe as $table) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $totalRows += $count;

        if (!$apply) {
            echo "  {$table}: {$count} row(s) would be deleted\n";
            continue;
        }

        echo "  {$table}: deleting {$count} row(s)... ";
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        echo "done.\n";
    }
} finally {
    if ($apply) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

echo "\nKept untouched: " . implode(', ', $keep) . "\n";

if (!$apply) {
    echo "\n{$totalRows} total row(s) across " . count($wipe) . " table(s) would be deleted.\n";
    echo "Re-run with --apply to actually wipe. Make sure you have a database backup first.\n";
} else {
    echo "\nDone — {$totalRows} total row(s) wiped across " . count($wipe) . " table(s).\n";
}
