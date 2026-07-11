<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * No household_id here — like transaction_splits and goal_contributions,
 * this is a pure child of a household-scoped parent (transactions), so
 * authorization always goes through the caller looking up the parent
 * transaction first. findByIdForTransaction() adds a second belt-and-
 * braces check that the attachment actually belongs to that transaction.
 */
final class AttachmentRepository
{
    public function create(int $transactionId, int $userId, string $originalFilename, string $storedFilename, string $mimeType, int $fileSize): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO attachments (transaction_id, uploaded_by_user_id, original_filename, stored_filename, mime_type, file_size, created_at)
             VALUES (:transaction_id, :uploaded_by_user_id, :original_filename, :stored_filename, :mime_type, :file_size, :created_at)'
        );

        $stmt->execute([
            'transaction_id' => $transactionId,
            'uploaded_by_user_id' => $userId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) Connection::get()->lastInsertId();
    }

    public function findByIdForTransaction(int $attachmentId, int $transactionId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM attachments WHERE id = :id AND transaction_id = :transaction_id LIMIT 1'
        );

        $stmt->execute(['id' => $attachmentId, 'transaction_id' => $transactionId]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function listForTransaction(int $transactionId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM attachments WHERE transaction_id = :transaction_id ORDER BY created_at'
        );

        $stmt->execute(['transaction_id' => $transactionId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, list<array>> keyed by transaction_id
     */
    public function listForTransactions(array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT * FROM attachments WHERE transaction_id IN ({$placeholders}) ORDER BY created_at"
        );
        $stmt->execute(array_values($transactionIds));

        $byTransaction = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTransaction[(int) $row['transaction_id']][] = $row;
        }

        return $byTransaction;
    }

    public function delete(int $attachmentId): void
    {
        Connection::get()->prepare('DELETE FROM attachments WHERE id = :id')->execute(['id' => $attachmentId]);
    }
}
