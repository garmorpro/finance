<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class TransactionSplitRepository
{
    /**
     * Replaces every split line for a transaction in one go — like
     * TagRepository::setTagsForTransaction(), the caller always sends the
     * complete desired set, not an incremental add/remove. An empty array
     * just clears the transaction back to unsplit.
     *
     * @param list<array{category_id: ?int, amount: string}> $splits
     */
    public function replaceForTransaction(int $transactionId, array $splits): void
    {
        $pdo = Connection::get();

        $pdo->prepare('DELETE FROM transaction_splits WHERE transaction_id = :transaction_id')
            ->execute(['transaction_id' => $transactionId]);

        if ($splits === []) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $insert = $pdo->prepare(
            'INSERT INTO transaction_splits (transaction_id, category_id, amount, created_at)
             VALUES (:transaction_id, :category_id, :amount, :created_at)'
        );

        foreach ($splits as $split) {
            $insert->execute([
                'transaction_id' => $transactionId,
                'category_id' => $split['category_id'],
                'amount' => $split['amount'],
                'created_at' => $now,
            ]);
        }
    }

    public function listForTransaction(int $transactionId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT ts.*, c.name AS category_name, c.color AS category_color
             FROM transaction_splits ts
             LEFT JOIN categories c ON c.id = ts.category_id
             WHERE ts.transaction_id = :transaction_id
             ORDER BY ts.id'
        );

        $stmt->execute(['transaction_id' => $transactionId]);

        return $stmt->fetchAll();
    }

    /**
     * @param list<int> $transactionIds
     * @return array<int, list<array>> keyed by transaction_id
     */
    public function listForTransactions(array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT ts.*, c.name AS category_name
             FROM transaction_splits ts
             LEFT JOIN categories c ON c.id = ts.category_id
             WHERE ts.transaction_id IN ({$placeholders})
             ORDER BY ts.id"
        );
        $stmt->execute(array_values($transactionIds));

        $byTransaction = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTransaction[(int) $row['transaction_id']][] = $row;
        }

        return $byTransaction;
    }
}
