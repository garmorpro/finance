<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AccountRepository;
use App\Repositories\TransactionRepository;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

/**
 * Transfers are two linked transaction rows (transfer_pair_id) created
 * and balanced together. This exercises the same sequence of repository
 * calls TransactionController::storeTransfer()/destroy() make — see
 * DatabaseTestCase's class doc for why this is tested at the repository
 * layer rather than by calling the controller directly.
 */
final class TransferIntegrationTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_a_transfer_moves_money_between_two_accounts_without_creating_it(): void
    {
        $household = $this->makeHousehold();
        $accountRepo = new AccountRepository();
        $transactionRepo = new TransactionRepository();

        $fromId = $this->makeAccount($household['household_id'], $household['user_id'], ['name' => 'Checking', 'current_balance' => '500.00']);
        $toId = $this->makeAccount($household['household_id'], $household['user_id'], ['name' => 'Savings', 'current_balance' => '100.00']);

        $this->createTransferPair($household['household_id'], $household['user_id'], $fromId, $toId, '200.00');

        $from = $accountRepo->findById($fromId, $household['household_id']);
        $to = $accountRepo->findById($toId, $household['household_id']);

        $this->assertSame('300.00', $from['current_balance']);
        $this->assertSame('300.00', $to['current_balance']);
    }

    public function test_both_sides_of_a_transfer_are_linked_to_each_other(): void
    {
        $household = $this->makeHousehold();
        $transactionRepo = new TransactionRepository();

        $fromId = $this->makeAccount($household['household_id'], $household['user_id']);
        $toId = $this->makeAccount($household['household_id'], $household['user_id']);

        ['fromTransactionId' => $fromTxId, 'toTransactionId' => $toTxId] =
            $this->createTransferPair($household['household_id'], $household['user_id'], $fromId, $toId, '50.00');

        $fromTx = $transactionRepo->findById($fromTxId, $household['household_id']);
        $toTx = $transactionRepo->findById($toTxId, $household['household_id']);

        $this->assertSame($toTxId, (int) $fromTx['transfer_pair_id']);
        $this->assertSame($fromTxId, (int) $toTx['transfer_pair_id']);
        $this->assertSame('transfer', $fromTx['transaction_type']);
        $this->assertSame('-50.00', $fromTx['amount']);
        $this->assertSame('50.00', $toTx['amount']);
    }

    public function test_transfers_are_excluded_from_budget_and_reports(): void
    {
        $household = $this->makeHousehold();
        $transactionRepo = new TransactionRepository();

        $fromId = $this->makeAccount($household['household_id'], $household['user_id']);
        $toId = $this->makeAccount($household['household_id'], $household['user_id']);

        ['fromTransactionId' => $fromTxId] = $this->createTransferPair($household['household_id'], $household['user_id'], $fromId, $toId, '10.00');

        $fromTx = $transactionRepo->findById($fromTxId, $household['household_id']);

        $this->assertSame(1, (int) $fromTx['exclude_from_budget']);
        $this->assertSame(1, (int) $fromTx['exclude_from_reports']);
    }

    public function test_deleting_both_sides_of_a_transfer_reverses_both_balances(): void
    {
        $household = $this->makeHousehold();
        $accountRepo = new AccountRepository();
        $transactionRepo = new TransactionRepository();

        $fromId = $this->makeAccount($household['household_id'], $household['user_id'], ['current_balance' => '500.00']);
        $toId = $this->makeAccount($household['household_id'], $household['user_id'], ['current_balance' => '100.00']);

        ['fromTransactionId' => $fromTxId, 'toTransactionId' => $toTxId] =
            $this->createTransferPair($household['household_id'], $household['user_id'], $fromId, $toId, '200.00');

        // Mirrors TransactionController::deleteOneSideAndReverseBalance()
        // called on both sides of the pair.
        foreach ([[$fromTxId, $fromId], [$toTxId, $toId]] as [$txId, $acctId]) {
            $tx = $transactionRepo->findById($txId, $household['household_id']);
            $transactionRepo->softDelete($txId, $household['household_id']);
            $account = $accountRepo->findById($acctId, $household['household_id']);
            $accountRepo->updateBalance($acctId, $household['household_id'], bcsub($account['current_balance'], $tx['amount'], 2));
        }

        $from = $accountRepo->findById($fromId, $household['household_id']);
        $to = $accountRepo->findById($toId, $household['household_id']);

        $this->assertSame('500.00', $from['current_balance']);
        $this->assertSame('100.00', $to['current_balance']);
        $this->assertNull($transactionRepo->findById($fromTxId, $household['household_id']));
        $this->assertNull($transactionRepo->findById($toTxId, $household['household_id']));
    }

    /**
     * @return array{fromTransactionId: int, toTransactionId: int}
     */
    private function createTransferPair(int $householdId, int $userId, int $fromAccountId, int $toAccountId, string $amount): array
    {
        $accountRepo = new AccountRepository();
        $transactionRepo = new TransactionRepository();

        $fromAccount = $accountRepo->findById($fromAccountId, $householdId);
        $toAccount = $accountRepo->findById($toAccountId, $householdId);

        $fromTxId = $transactionRepo->create($householdId, $userId, $this->transactionData([
            'account_id' => $fromAccountId,
            'category_id' => null,
            'transaction_type' => 'transfer',
            'payee' => 'Transfer to Savings',
            'exclude_from_budget' => true,
            'exclude_from_reports' => true,
            'signed_amount' => bcmul($amount, '-1', 2),
        ]));

        $toTxId = $transactionRepo->create($householdId, $userId, $this->transactionData([
            'account_id' => $toAccountId,
            'category_id' => null,
            'transaction_type' => 'transfer',
            'payee' => 'Transfer from Checking',
            'exclude_from_budget' => true,
            'exclude_from_reports' => true,
            'signed_amount' => $amount,
        ]));

        $transactionRepo->linkTransferPair($fromTxId, $householdId, $toTxId);
        $transactionRepo->linkTransferPair($toTxId, $householdId, $fromTxId);

        $accountRepo->updateBalance($fromAccountId, $householdId, bcsub($fromAccount['current_balance'], $amount, 2));
        $accountRepo->updateBalance($toAccountId, $householdId, bcadd($toAccount['current_balance'], $amount, 2));

        return ['fromTransactionId' => $fromTxId, 'toTransactionId' => $toTxId];
    }
}
