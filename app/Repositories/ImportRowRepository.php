<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Support\FieldCipher;

final class ImportRowRepository
{
    public function record(
        int $importId,
        int $rowNumber,
        string $rawData,
        string $status,
        ?int $transactionId,
        ?string $message
    ): void {
        $stmt = Connection::get()->prepare(
            'INSERT INTO import_rows (import_id, transaction_id, row_index, raw_data, raw_data_encrypted, status, message, created_at)
             VALUES (:import_id, :transaction_id, :row_index, NULL, :raw_data_encrypted, :status, :message, :created_at)'
        );

        $stmt->execute([
            'import_id' => $importId,
            'transaction_id' => $transactionId,
            'row_index' => $rowNumber,
            'raw_data_encrypted' => FieldCipher::encrypt($rawData),
            'status' => $status,
            'message' => $message,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function listRejectedForImport(int $importId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM import_rows WHERE import_id = :import_id AND status = "rejected" ORDER BY row_index'
        );

        $stmt->execute(['import_id' => $importId]);

        return array_map(self::hydrate(...), $stmt->fetchAll());
    }

    /**
     * raw_data (the full original CSV line) is encrypted — see
     * App\Support\FieldCipher — since it's a second, full copy of every
     * imported row's payee/amount/date, independent of transactions.amount/
     * payee/notes. Falls back to the legacy plaintext column for any row
     * not yet backfilled.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $row['raw_data'] = FieldCipher::decryptOrFallback($row['raw_data_encrypted'] ?? null, $row['raw_data']);
        unset($row['raw_data_encrypted']);

        return $row;
    }
}
