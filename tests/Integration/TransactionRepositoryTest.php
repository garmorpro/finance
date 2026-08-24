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
        ]));

        $updated = $transactionRepo->findById($transactionId, $household['household_id']);

        $this->assertSame('Updated', $updated['payee']);
        $this->assertSame('-99.99', $updated['amount']);
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

    /**
     * amount/payee/notes are encrypted (see App\Support\FieldCipher) — an
     * amount-range filter, a payee/notes search, and a sort by amount/
     * payee all route through listForHouseholdViaPhp() instead of plain
     * SQL. These exercise that path end-to-end through the public API,
     * the same way every other test in this file already exercises
     * create()/update()'s encryption transparently.
     */
    public function test_list_for_household_amount_range_filter_matches_unsigned_magnitude(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactionRepo = new TransactionRepository();

        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'signed_amount' => '-5.00', 'payee' => 'Small',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'signed_amount' => '-150.00', 'payee' => 'Big',
        ]));

        $result = $transactionRepo->listForHousehold($household['household_id'], ['amount_min' => '100']);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Big', $result['rows'][0]['payee']);
    }

    public function test_list_for_household_search_matches_payee_and_notes_case_insensitively(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactionRepo = new TransactionRepository();

        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'payee' => 'Whole Foods Market',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'payee' => 'Amazon', 'notes' => 'birthday gift for mom',
        ]));

        $byPayee = $transactionRepo->listForHousehold($household['household_id'], ['search' => 'whole foods']);
        $byNotes = $transactionRepo->listForHousehold($household['household_id'], ['search' => 'birthday']);

        $this->assertSame(1, $byPayee['total']);
        $this->assertSame('Whole Foods Market', $byPayee['rows'][0]['payee']);
        $this->assertSame(1, $byNotes['total']);
        $this->assertSame('Amazon', $byNotes['rows'][0]['payee']);
    }

    public function test_list_for_household_sorts_by_amount_ascending(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactionRepo = new TransactionRepository();

        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'signed_amount' => '-10.00', 'payee' => 'Ten',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'signed_amount' => '-50.00', 'payee' => 'Fifty',
        ]));

        $result = $transactionRepo->listForHousehold($household['household_id'], [], 1, 'amount', 'asc');

        $this->assertSame('Fifty', $result['rows'][0]['payee']);
        $this->assertSame('Ten', $result['rows'][1]['payee']);
    }

    public function test_sums_for_household_excludes_transfers_and_respects_amount_filter(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $toAccountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactionRepo = new TransactionRepository();

        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'transaction_type' => 'income', 'signed_amount' => '1000.00',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'transaction_type' => 'expense', 'signed_amount' => '-40.00',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId, 'transaction_type' => 'transfer', 'signed_amount' => '-500.00',
        ]));

        $sums = $transactionRepo->sumsForHousehold($household['household_id'], []);

        $this->assertSame('1000.00', $sums['income']);
        $this->assertSame('-40.00', $sums['expenses']);
        $this->assertSame('960.00', $sums['net']);

        $filtered = $transactionRepo->sumsForHousehold($household['household_id'], ['amount_min' => '500']);
        $this->assertSame('1000.00', $filtered['income']);
        $this->assertSame('0.00', $filtered['expenses']);
    }
}
