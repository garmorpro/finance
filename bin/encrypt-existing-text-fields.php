<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Support\FieldCipher;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * One-time backfill: encrypts every existing accounts/financial_goals/
 * recurring_items row's identifying text (name, institution_name, notes,
 * description — see docs/security.md's "Encryption at rest" section)
 * into the new *_encrypted columns (migrations 0046-0048), then clears
 * the old plaintext column. Safe to run more than once — only rows
 * where the *_encrypted column is still NULL and the old plaintext
 * column actually has something in it are touched, so a second run (or
 * a run after new rows were created going forward through the app,
 * which already writes only to *_encrypted) does nothing.
 *
 * Requires ENCRYPTION_KEY in .env — see .env.example for how to
 * generate one. Refuses to run at all without it, rather than silently
 * leaving data in plaintext.
 *
 * Defaults to a dry run — prints how many rows per table/column would
 * be encrypted, without writing anything. Pass --apply to actually do
 * it. BACK UP YOUR DATABASE FIRST — this changes real financial data,
 * and losing ENCRYPTION_KEY afterward makes the encrypted values
 * permanently unreadable (see docs/security.md).
 *
 *   php bin/encrypt-existing-text-fields.php
 *   php bin/encrypt-existing-text-fields.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

if (!FieldCipher::isConfigured()) {
    echo "ENCRYPTION_KEY is not configured in .env (or is not a valid base64-encoded 32-byte key).\n";
    echo "Generate one with:\n";
    echo "  php -r \"echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;\"\n";
    echo "then set ENCRYPTION_KEY to that value in .env before running this again.\n";
    exit(1);
}

$pdo = Connection::get();

/**
 * @param list<string> $textColumns plaintext column names to encrypt
 * @return array{rows: int, fields: int}
 */
function encryptTable(PDO $pdo, string $table, array $textColumns, bool $apply): array
{
    $whereParts = array_map(
        fn (string $col): string => "({$col} IS NOT NULL AND {$col}_encrypted IS NULL)",
        $textColumns
    );
    $where = implode(' OR ', $whereParts);

    $stmt = $pdo->query("SELECT id, " . implode(', ', $textColumns) . " FROM {$table} WHERE {$where}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldsEncrypted = 0;

    foreach ($rows as $row) {
        $setClauses = [];
        $params = ['id' => $row['id']];

        foreach ($textColumns as $col) {
            if ($row[$col] === null) {
                continue;
            }

            $fieldsEncrypted++;
            $setClauses[] = "{$col} = NULL, {$col}_encrypted = :{$col}_encrypted";
            $params["{$col}_encrypted"] = FieldCipher::encrypt($row[$col]);
        }

        if ($setClauses === [] || !$apply) {
            continue;
        }

        $update = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE id = :id");
        $update->execute($params);
    }

    return ['rows' => count($rows), 'fields' => $fieldsEncrypted];
}

$tables = [
    'accounts' => ['name', 'institution_name', 'notes'],
    'financial_goals' => ['name', 'description'],
    'recurring_items' => ['name', 'notes'],
];

echo $apply
    ? "Encrypting existing text fields — writing changes.\n\n"
    : "Dry run — nothing will be written. Re-run with --apply to actually encrypt.\n\n";

$totalRows = 0;
$totalFields = 0;

foreach ($tables as $table => $columns) {
    $result = encryptTable($pdo, $table, $columns, $apply);
    $totalRows += $result['rows'];
    $totalFields += $result['fields'];

    echo "  {$table} (" . implode(', ', $columns) . "): {$result['rows']} row(s), {$result['fields']} field(s) "
        . ($apply ? 'encrypted' : 'would be encrypted') . "\n";
}

echo "\n{$totalRows} total row(s), {$totalFields} total field(s) " . ($apply ? 'encrypted.' : 'would be encrypted.') . "\n";

if (!$apply) {
    echo "Re-run with --apply to actually encrypt. Make sure you have a database backup first.\n";
}
