<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\TransactionRepository;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

final class TransactionRepositoryTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_create_stores_a_signed_expense_amount(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'signed_amount' => '-42.50',
            'payee' => 'Grocery Store',
        ]));

        $transaction = $transactionRepo->findById($transactionId, $household['household_id']);

        $this->assertSame('-42.50', $transaction['amount']);
        $this->assertSame('expense', $transaction['transaction_type']);
        $this->assertSame('Grocery Store', $transaction['payee']);
        $this->assertSame(0, (int) $transaction['is_reviewed']);
    }

    public function test_create_stores_a_positive_income_amount(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'signed_amount' => '1500.00',
            'payee' => 'Paycheck',
        ]));

        $transaction = $transactionRepo->findById($transactionId, $household['household_id']);

        $this->assertSame('1500.00', $transaction['amount']);
    }

    public function test_update_changes_the_stored_fields(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'payee' => 'Original',
        ]));

        $transactionRepo->update($transactionId, $household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'payee' => 'Updated',
            'signed_amount' => '-99.99',
            'is_reviewed' => true,
        ]));

        $updated = $transactionRepo->findById($transactionId, $household['household_id']);

        $this->assertSame('Updated', $updated['payee']);
        $this->assertSame('-99.99', $updated['amount']);
        $this->assertSame(1, (int) $updated['is_reviewed']);
    }

    public function test_soft_deleted_transactions_are_excluded_from_find(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
        ]));

        $transactionRepo->softDelete($transactionId, $household['household_id']);

        $this->assertNull($transactionRepo->findById($transactionId, $household['household_id']));
    }

    public function test_exists_similar_detects_a_likely_duplicate(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_date' => '2026-03-15',
            'signed_amount' => '-25.00',
            'payee' => 'Coffee Shop',
        ]));

        $this->assertTrue($transactionRepo->existsSimilar(
            $household['household_id'],
            $accountId,
            '2026-03-15',
            '-25.00',
            'Coffee Shop'
        ));
    }

    public function test_exists_similar_does_not_flag_a_different_amount_as_a_duplicate(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_date' => '2026-03-15',
            'signed_amount' => '-25.00',
            'payee' => 'Coffee Shop',
        ]));

        $this->assertFalse($transactionRepo->existsSimilar(
            $household['household_id'],
            $accountId,
            '2026-03-15',
            '-30.00',
            'Coffee Shop'
        ));
    }

    public function test_exists_similar_does_not_match_a_soft_deleted_transaction(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_date' => '2026-03-15',
            'signed_amount' => '-25.00',
            'payee' => 'Coffee Shop',
        ]));
        $transactionRepo->softDelete($transactionId, $household['household_id']);

        $this->assertFalse($transactionRepo->existsSimilar(
            $household['household_id'],
            $accountId,
            '2026-03-15',
            '-25.00',
            'Coffee Shop'
        ));
    }

    public function test_unreviewed_counts_splits_by_before_today(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_date' => date('Y-m-d', strtotime('-3 days')),
            'is_reviewed' => false,
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_date' => gmdate('Y-m-d'),
            'is_reviewed' => false,
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'is_reviewed' => true,
        ]));

        $counts = $transactionRepo->unreviewedCounts($household['household_id']);

        $this->assertSame(2, $counts['total']);
        $this->assertSame(1, $counts['before_today']);
    }
}
