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

        $result = (new TransactionRepository())->listForHousehold($householdId, $filters, $page);
        $accountRepo = new AccountRepository();

        Response::html(View::render('transactions/index', [
            'transactions' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'accounts' => $accountRepo->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'filters' => $filters,
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
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

        $transaction = (new TransactionRepository())->findById($transactionId, $householdId);

        if ($transaction === null) {
            Response::html('Transaction not found.', 404);
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
        $accountId = (int) $transaction['account_id'];

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo->softDelete($transactionId, $householdId);

            $account = $accountRepo->findById($accountId, $householdId);
            $newBalance = bcsub($account['current_balance'], $transaction['amount'], 2);

            (new AccountBalanceHistoryRepository())->record(
                $accountId,
                $userId,
                $account['current_balance'],
                $newBalance,
                'Transaction deleted: ' . $transaction['payee']
            );
            $accountRepo->updateBalance($accountId, $householdId, $newBalance);

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transaction.deleted',
                'transaction',
                $transactionId,
                $request->ip()
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['_flash_error'] = 'Something went wrong deleting that transaction. Please try again.';
            header('Location: /transactions');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transaction deleted.';
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
