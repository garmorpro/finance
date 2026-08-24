<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Support\FieldCipher;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * One-time backfill: encrypts every existing accounts row's balance/
 * limit/payment fields (current_balance, available_balance,
 * credit_limit, minimum_payment, original_balance) and every existing
 * account_balance_history row's previous_balance/new_balance into the
 * new *_encrypted columns (migrations 0049-0050), then clears the old
 * plaintext column — see docs/security.md's "Encryption at rest"
 * section. A separate script from bin/encrypt-existing-text-fields.php
 * (name/notes/description) deliberately, so this higher-stakes pass —
 * real dollar amounts, not display text — can be run, reviewed, and
 * rolled back independently.
 *
 * Same conventions as encrypt-existing-text-fields.php: safe to run
 * more than once (only untouched rows are touched), requires
 * ENCRYPTION_KEY in .env, defaults to a dry run, --apply to actually
 * write. BACK UP YOUR DATABASE FIRST — this changes real financial
 * data, and losing ENCRYPTION_KEY afterward makes the encrypted values
 * permanently unreadable (see docs/security.md).
 *
 *   php bin/encrypt-existing-balance-fields.php
 *   php bin/encrypt-existing-balance-fields.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

if (!FieldCipher::isConfigured()) {
    echo "ENCRYPTION_KEY is not configured in .env (or is not a valid base64-encoded 32-byte key).\n";
    echo "Generate one with:\n";
    echo "  php -r \"echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;\"\n";
    echo "then set ENCRYPTION_KEY to that value in .env before running this again.\n";
    echo "(If you've already run bin/encrypt-existing-text-fields.php, reuse that same key here —\n";
    echo "one key for the whole app, not a separate one per table.)\n";
    exit(1);
}

$pdo = Connection::get();

/**
 * @param list<string> $numericColumns plaintext column names to encrypt
 * @return array{rows: int, fields: int}
 */
function encryptBalanceTable(PDO $pdo, string $table, array $numericColumns, bool $apply): array
{
    $whereParts = array_map(
        fn (string $col): string => "({$col} IS NOT NULL AND {$col}_encrypted IS NULL)",
        $numericColumns
    );
    $where = implode(' OR ', $whereParts);

    $stmt = $pdo->query("SELECT id, " . implode(', ', $numericColumns) . " FROM {$table} WHERE {$where}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldsEncrypted = 0;

    foreach ($rows as $row) {
        $setClauses = [];
        $params = ['id' => $row['id']];

        foreach ($numericColumns as $col) {
            if ($row[$col] === null) {
                continue;
            }

            $fieldsEncrypted++;
            $setClauses[] = "{$col} = NULL, {$col}_encrypted = :{$col}_encrypted";
            // $row[$col] is a DECIMAL column fetched via PDO, which
            // returns it as a numeric string (e.g. "1109.00") — the
            // exact same shape FieldCipher already handles for text,
            // and the same shape this app's bcmath calls expect back
            // out on decrypt.
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
    'accounts' => ['current_balance', 'available_balance', 'credit_limit', 'minimum_payment', 'original_balance'],
    'account_balance_history' => ['previous_balance', 'new_balance'],
];

echo $apply
    ? "Encrypting existing balance fields — writing changes.\n\n"
    : "Dry run — nothing will be written. Re-run with --apply to actually encrypt.\n\n";

$totalRows = 0;
$totalFields = 0;

foreach ($tables as $table => $columns) {
    $result = encryptBalanceTable($pdo, $table, $columns, $apply);
    $totalRows += $result['rows'];
    $totalFields += $result['fields'];

    echo "  {$table} (" . implode(', ', $columns) . "): {$result['rows']} row(s), {$result['fields']} field(s) "
        . ($apply ? 'encrypted' : 'would be encrypted') . "\n";
}

echo "\n{$totalRows} total row(s), {$totalFields} total field(s) " . ($apply ? 'encrypted.' : 'would be encrypted.') . "\n";

if (!$apply) {
    echo "Re-run with --apply to actually encrypt. Make sure you have a database backup first.\n";
}
