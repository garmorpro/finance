<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TransactionRepository;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class TransactionController
{
    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $filters = [
            'account_id' => $request->query('account_id'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'reviewed' => $request->query('reviewed'),
            'search' => $request->query('search'),
        ];

        $page = max(1, (int) $request->query('page', '1'));

        $transactionRepo = new TransactionRepository();
        $result = $transactionRepo->listForHousehold($householdId, $filters, $page);
        $accountRepo = new AccountRepository();

        Response::html(View::render('transactions/index', [
            'transactions' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'accounts' => $accountRepo->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'filters' => $filters,
            'unreviewedCount' => $transactionRepo->unreviewedCounts($householdId)['total'],
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
    }

    /**
     * The daily review queue: every unreviewed transaction, grouped by
     * date, so nothing from a missed day quietly falls off the bottom of
     * the regular transactions list.
     */
    public function review(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $transactionRepo = new TransactionRepository();

        $transactions = $transactionRepo->unreviewedForHousehold($householdId);
        $counts = $transactionRepo->unreviewedCounts($householdId);

        $groups = [];
        foreach ($transactions as $transaction) {
            $groups[$transaction['transaction_date']][] = $transaction;
        }

        Response::html(View::render('transactions/review', [
            'groups' => $groups,
            'loadedCount' => count($transactions),
            'counts' => $counts,
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
    }

    public function markReviewed(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions/review');
            return;
        }

        (new TransactionRepository())->markReviewed($transactionId, $householdId, $userId);

        header('Location: /transactions/review');
    }

    public function markAllReviewed(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions/review');
            return;
        }

        $count = (new TransactionRepository())->markAllReviewed($householdId, $userId);

        $_SESSION['_flash_notice'] = $count > 0
            ? 'Marked ' . $count . ' transaction' . ($count === 1 ? '' : 's') . ' as reviewed.'
            : 'Nothing to review.';
        header('Location: /transactions/review');
    }

    public function export(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $filters = [
            'account_id' => $request->query('account_id'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'reviewed' => $request->query('reviewed'),
            'search' => $request->query('search'),
        ];

        $rows = (new TransactionRepository())->exportForHousehold($householdId, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Payee', 'Category', 'Account', 'Type', 'Amount', 'Reviewed', 'Notes']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['transaction_date'],
                $row['payee'],
                $row['category_name'] ?? '',
                $row['account_name'],
                $row['transaction_type'],
                $row['amount'],
                (int) $row['is_reviewed'] === 1 ? 'yes' : 'no',
                $row['notes'] ?? '',
            ]);
        }

        fclose($out);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        $this->renderForm('transactions/create', [
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]);

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /transactions/create');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input, $householdId);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById((int) $input['account_id'], $householdId);

        $signedAmount = $this->signedAmount($input['transaction_type'], $input['amount']);

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionId = (new TransactionRepository())->create($householdId, $userId, [
                ...$input,
                'signed_amount' => $signedAmount,
            ]);

            $newBalance = bcadd($account['current_balance'], $signedAmount, 2);

            (new AccountBalanceHistoryRepository())->record(
                (int) $input['account_id'],
                $userId,
                $account['current_balance'],
                $newBalance,
                'Transaction: ' . $input['payee']
            );
            $accountRepo->updateBalance((int) $input['account_id'], $householdId, $newBalance);

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transaction.created',
                'transaction',
                $transactionId,
                $request->ip(),
                ['payee' => $input['payee'], 'amount' => $signedAmount]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transaction. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transaction added.';
        header('Location: /transactions');
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $transactionRepo = new TransactionRepository();
        $transaction = $transactionRepo->findById($transactionId, $householdId);

        if ($transaction === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        // Transfers are two linked rows — editing the amount or account on
        // just one side would desync the balances, so they get a reduced
        // edit view (notes/reviewed only) with delete as the way to "undo."
        if ($transaction['transaction_type'] === 'transfer') {
            $pairAccountName = null;
            if ($transaction['transfer_pair_id'] !== null) {
                $pair = $transactionRepo->findById((int) $transaction['transfer_pair_id'], $householdId);
                $pairAccountName = $pair !== null ? (new AccountRepository())->findById((int) $pair['account_id'], $householdId)['name'] ?? null : null;
            }

            Response::html(View::render('transactions/edit-transfer', [
                'transaction' => $transaction,
                'pairAccountName' => $pairAccountName,
                'csrfToken' => Csrf::token(),
                'error' => $_SESSION['_flash_error'] ?? null,
            ]));

            unset($_SESSION['_flash_error']);
            return;
        }

        $this->renderForm('transactions/edit', [
            'transaction' => $transaction,
            'error' => $_SESSION['_flash_error'] ?? null,
        ]);

        unset($_SESSION['_flash_error']);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $transactionRepo = new TransactionRepository();
        $existing = $transactionRepo->findById($transactionId, $householdId);

        if ($existing === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        if ($existing['transaction_type'] === 'transfer') {
            $this->updateTransferNotes($request, $transactionRepo, $transactionId, $householdId, $userId);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($transactionId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /transactions/' . $transactionId . '/edit');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input, $householdId);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo = new AccountRepository();
        $newSignedAmount = $this->signedAmount($input['transaction_type'], $input['amount']);
        $oldSignedAmount = $existing['amount'];
        $oldAccountId = (int) $existing['account_id'];
        $newAccountId = (int) $input['account_id'];

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo->update($transactionId, $householdId, $userId, [
                ...$input,
                'signed_amount' => $newSignedAmount,
            ]);

            $historyRepo = new AccountBalanceHistoryRepository();

            if ($oldAccountId === $newAccountId) {
                $account = $accountRepo->findById($newAccountId, $householdId);
                $reverted = bcsub($account['current_balance'], $oldSignedAmount, 2);
                $newBalance = bcadd($reverted, $newSignedAmount, 2);

                $historyRepo->record($newAccountId, $userId, $account['current_balance'], $newBalance, 'Transaction edited: ' . $input['payee']);
                $accountRepo->updateBalance($newAccountId, $householdId, $newBalance);
            } else {
                $oldAccount = $accountRepo->findById($oldAccountId, $householdId);
                $revertedOld = bcsub($oldAccount['current_balance'], $oldSignedAmount, 2);
                $historyRepo->record($oldAccountId, $userId, $oldAccount['current_balance'], $revertedOld, 'Transaction moved to another account: ' . $input['payee']);
                $accountRepo->updateBalance($oldAccountId, $householdId, $revertedOld);

                $newAccount = $accountRepo->findById($newAccountId, $householdId);
                $appliedNew = bcadd($newAccount['current_balance'], $newSignedAmount, 2);
                $historyRepo->record($newAccountId, $userId, $newAccount['current_balance'], $appliedNew, 'Transaction moved from another account: ' . $input['payee']);
                $accountRepo->updateBalance($newAccountId, $householdId, $appliedNew);
            }

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transaction.updated',
                'transaction',
                $transactionId,
                $request->ip()
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transaction. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transaction updated.';
        header('Location: /transactions');
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $transactionRepo = new TransactionRepository();
        $transaction = $transactionRepo->findById($transactionId, $householdId);

        if ($transaction === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions');
            return;
        }

        $accountRepo = new AccountRepository();
        $historyRepo = new AccountBalanceHistoryRepository();
        $auditRepo = new AuditLogRepository();

        $pair = $transaction['transfer_pair_id'] !== null
            ? $transactionRepo->findById((int) $transaction['transfer_pair_id'], $householdId)
            : null;

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $transaction, $householdId, $userId, $request->ip());

            // A transfer is two rows representing one real-world event —
            // deleting only one side would leave the other's balance
            // effect applied with no matching counterpart, so both sides
            // are deleted together or not at all.
            if ($pair !== null) {
                $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $pair, $householdId, $userId, $request->ip());
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['_flash_error'] = 'Something went wrong deleting that transaction. Please try again.';
            header('Location: /transactions');
            return;
        }

        $_SESSION['_flash_notice'] = $pair !== null ? 'Transfer deleted.' : 'Transaction deleted.';
        header('Location: /transactions');
    }

    private function deleteOneSideAndReverseBalance(
        TransactionRepository $transactionRepo,
        AccountRepository $accountRepo,
        AccountBalanceHistoryRepository $historyRepo,
        AuditLogRepository $auditRepo,
        array $transaction,
        int $householdId,
        int $userId,
        string $ip
    ): void {
        $transactionRepo->softDelete((int) $transaction['id'], $householdId);

        $accountId = (int) $transaction['account_id'];
        $account = $accountRepo->findById($accountId, $householdId);
        $newBalance = bcsub($account['current_balance'], $transaction['amount'], 2);

        $historyRepo->record($accountId, $userId, $account['current_balance'], $newBalance, 'Transaction deleted: ' . $transaction['payee']);
        $accountRepo->updateBalance($accountId, $householdId, $newBalance);

        $auditRepo->log($userId, $householdId, 'transaction.deleted', 'transaction', (int) $transaction['id'], $ip);
    }

    private function updateTransferNotes(
        Request $request,
        TransactionRepository $transactionRepo,
        int $transactionId,
        int $householdId,
        int $userId
    ): void {
        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions/' . $transactionId . '/edit');
            return;
        }

        $transactionRepo->updateNotesAndReviewed(
            $transactionId,
            $householdId,
            $userId,
            trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
            $request->post('is_reviewed') === '1'
        );

        (new AuditLogRepository())->log($userId, $householdId, 'transaction.updated', 'transaction', $transactionId, $request->ip());

        $_SESSION['_flash_notice'] = 'Transfer updated.';
        header('Location: /transactions');
    }

    public function showTransferForm(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render('transactions/transfer', [
            'accounts' => (new AccountRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    public function storeTransfer(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $input = [
            'from_account_id' => $request->post('from_account_id'),
            'to_account_id' => $request->post('to_account_id'),
            'transaction_date' => $request->post('transaction_date'),
            'amount' => trim($request->post('amount')),
            'notes' => trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
        ];

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /transactions/transfer');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if (in_array($input['from_account_id'], ['', null], true) || in_array($input['to_account_id'], ['', null], true)) {
            $redirectBack('Please choose both accounts.');
            return;
        }

        if ($input['from_account_id'] === $input['to_account_id']) {
            $redirectBack('Please choose two different accounts.');
            return;
        }

        if (!MoneyInput::isValid($input['amount']) || bccomp($input['amount'], '0', 2) <= 0) {
            $redirectBack('Please enter a valid amount greater than zero.');
            return;
        }

        if (($input['transaction_date'] ?? '') === '' || \DateTime::createFromFormat('Y-m-d', $input['transaction_date']) === false) {
            $redirectBack('Please enter a valid transaction date.');
            return;
        }

        $accountRepo = new AccountRepository();
        $fromAccount = $accountRepo->findById((int) $input['from_account_id'], $householdId);
        $toAccount = $accountRepo->findById((int) $input['to_account_id'], $householdId);

        if ($fromAccount === null || $toAccount === null) {
            $redirectBack('Please choose valid accounts.');
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo = new TransactionRepository();
            $historyRepo = new AccountBalanceHistoryRepository();
            $auditRepo = new AuditLogRepository();

            $fromId = $transactionRepo->create($householdId, $userId, [
                'account_id' => $fromAccount['id'],
                'category_id' => null,
                'transaction_type' => 'transfer',
                'transaction_date' => $input['transaction_date'],
                'payee' => 'Transfer to ' . $toAccount['name'],
                'notes' => $input['notes'],
                'is_reviewed' => false,
                'exclude_from_budget' => true,
                'exclude_from_reports' => true,
                'signed_amount' => bcmul($input['amount'], '-1', 2),
            ]);

            $toId = $transactionRepo->create($householdId, $userId, [
                'account_id' => $toAccount['id'],
                'category_id' => null,
                'transaction_type' => 'transfer',
                'transaction_date' => $input['transaction_date'],
                'payee' => 'Transfer from ' . $fromAccount['name'],
                'notes' => $input['notes'],
                'is_reviewed' => false,
                'exclude_from_budget' => true,
                'exclude_from_reports' => true,
                'signed_amount' => $input['amount'],
            ]);

            $transactionRepo->linkTransferPair($fromId, $householdId, $toId);
            $transactionRepo->linkTransferPair($toId, $householdId, $fromId);

            $newFromBalance = bcsub($fromAccount['current_balance'], $input['amount'], 2);
            $historyRepo->record($fromAccount['id'], $userId, $fromAccount['current_balance'], $newFromBalance, 'Transfer to ' . $toAccount['name']);
            $accountRepo->updateBalance($fromAccount['id'], $householdId, $newFromBalance);

            $newToBalance = bcadd($toAccount['current_balance'], $input['amount'], 2);
            $historyRepo->record($toAccount['id'], $userId, $toAccount['current_balance'], $newToBalance, 'Transfer from ' . $fromAccount['name']);
            $accountRepo->updateBalance($toAccount['id'], $householdId, $newToBalance);

            $auditRepo->log($userId, $householdId, 'transaction.transfer_created', 'transaction', $fromId, $request->ip(), [
                'from_account' => $fromAccount['name'],
                'to_account' => $toAccount['name'],
                'amount' => $input['amount'],
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transfer. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transfer added.';
        header('Location: /transactions');
    }

    private function renderForm(string $view, array $extra): void
    {
        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render($view, [
            ...$extra,
            // Includes archived accounts: an edit form must still be able to
            // show the transaction's current account even if it was archived
            // after the transaction was created, or the dropdown would
            // silently reassign it to whatever option happens to be first.
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
        ]));
    }

    private function signedAmount(string $type, string $amount): string
    {
        return $type === 'expense' ? bcmul($amount, '-1', 2) : $amount;
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'account_id' => $request->post('account_id'),
            'category_id' => $request->post('category_id') !== '' ? $request->post('category_id') : null,
            'transaction_type' => $request->post('transaction_type'),
            'transaction_date' => $request->post('transaction_date'),
            'amount' => trim($request->post('amount')),
            'payee' => trim($request->post('payee')),
            'notes' => trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
            'is_reviewed' => $request->post('is_reviewed') === '1',
            'exclude_from_budget' => $request->post('exclude_from_budget') === '1',
            'exclude_from_reports' => $request->post('exclude_from_reports') === '1',
        ];
    }

    private function validate(array $input, int $householdId): ?string
    {
        if (in_array($input['account_id'], ['', null], true)) {
            return 'Please choose an account.';
        }

        if ((new AccountRepository())->findById((int) $input['account_id'], $householdId) === null) {
            return 'Please choose a valid account.';
        }

        if (!in_array($input['transaction_type'], ['income', 'expense'], true)) {
            return 'Please choose a valid transaction type.';
        }

        if ($input['payee'] === '') {
            return 'Payee is required.';
        }

        if (!MoneyInput::isValid($input['amount']) || bccomp($input['amount'], '0', 2) <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }

        if (($input['transaction_date'] ?? '') === '' || \DateTime::createFromFormat('Y-m-d', $input['transaction_date']) === false) {
            return 'Please enter a valid transaction date.';
        }

        if ($input['category_id'] !== null && (new CategoryRepository())->findById((int) $input['category_id'], $householdId) === null) {
            return 'Please choose a valid category.';
        }

        return null;
    }
}
