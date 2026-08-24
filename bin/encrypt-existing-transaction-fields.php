<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Support\FieldCipher;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * One-time backfill: encrypts every existing row's amount/payee/notes-class
 * fields into the new *_encrypted columns added by migrations 0051-0054,
 * then clears the old plaintext column — see docs/security.md's
 * "Encryption at rest" section (phase 3). Covers:
 *
 *   - transactions.amount / payee / notes
 *   - transaction_splits.amount
 *   - import_rows.raw_data (the original CSV line — a second copy of the
 *     same payee/amount/date data, found while auditing this phase)
 *   - account_balance_history.note (carries the transaction payee/
 *     recurring item name/transfer account name whenever a balance
 *     change came from one of those — also found during this audit,
 *     not part of the original balance-fields pass)
 *
 * A separate script from encrypt-existing-text-fields.php and
 * encrypt-existing-balance-fields.php deliberately, so this pass can be
 * run, reviewed, and rolled back independently, same reasoning as why
 * those two are already separate from each other.
 *
 * Same conventions as both: safe to run more than once (only untouched
 * rows are touched), requires ENCRYPTION_KEY in .env, defaults to a dry
 * run, --apply to actually write. BACK UP YOUR DATABASE FIRST — this
 * touches every transaction ever entered, and losing ENCRYPTION_KEY
 * afterward makes the encrypted values permanently unreadable (see
 * docs/security.md).
 *
 *   php bin/encrypt-existing-transaction-fields.php
 *   php bin/encrypt-existing-transaction-fields.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

if (!FieldCipher::isConfigured()) {
    echo "ENCRYPTION_KEY is not configured in .env (or is not a valid base64-encoded 32-byte key).\n";
    echo "Generate one with:\n";
    echo "  php -r \"echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;\"\n";
    echo "then set ENCRYPTION_KEY to that value in .env before running this again.\n";
    echo "(Reuse the same key you're already using for the other encrypt-existing-*.php scripts —\n";
    echo "one key for the whole app, not a separate one per table.)\n";
    exit(1);
}

$pdo = Connection::get();

/**
 * @param list<string> $columns plaintext column names to encrypt
 * @return array{rows: int, fields: int}
 */
function encryptTable(PDO $pdo, string $table, array $columns, bool $apply): array
{
    $whereParts = array_map(
        fn (string $col): string => "({$col} IS NOT NULL AND {$col}_encrypted IS NULL)",
        $columns
    );
    $where = implode(' OR ', $whereParts);

    $stmt = $pdo->query("SELECT id, " . implode(', ', $columns) . " FROM {$table} WHERE {$where}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldsEncrypted = 0;

    foreach ($rows as $row) {
        $setClauses = [];
        $params = ['id' => $row['id']];

        foreach ($columns as $col) {
            if ($row[$col] === null) {
                continue;
            }

            $fieldsEncrypted++;
            $setClauses[] = "{$col} = NULL, {$col}_encrypted = :{$col}_encrypted";
            // $row[$col] is a plain PHP string as PDO returns it — a
            // DECIMAL column comes back as a numeric string (e.g.
            // "42.50"), exactly the shape FieldCipher and this app's
            // bcmath calls already expect on the way back out.
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
    'transactions' => ['amount', 'payee', 'notes'],
    'transaction_splits' => ['amount'],
    'import_rows' => ['raw_data'],
    'account_balance_history' => ['note'],
];

echo $apply
    ? "Encrypting existing transaction fields — writing changes.\n\n"
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
