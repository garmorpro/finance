<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

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
            'INSERT INTO import_rows (import_id, transaction_id, row_index, raw_data, status, message, created_at)
             VALUES (:import_id, :transaction_id, :row_index, :raw_data, :status, :message, :created_at)'
        );

        $stmt->execute([
            'import_id' => $importId,
            'transaction_id' => $transactionId,
            'row_index' => $rowNumber,
            'raw_data' => $rawData,
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

        return $stmt->fetchAll();
    }
}
