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
use App\Repositories\RecurringItemRepository;
use App\Repositories\TransactionRepository;
use App\Services\RuleMatchingService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class RecurringController
{
    private const FREQUENCIES = ['weekly', 'biweekly', 'monthly', 'quarterly', 'annually'];

    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $recurringRepo = new RecurringItemRepository();
        $items = $recurringRepo->listForHousehold($householdId);
        $today = gmdate('Y-m-d');

        $active = array_values(array_filter($items, fn (array $i): bool => $i['status'] === 'active'));
        $canceled = array_values(array_filter($items, fn (array $i): bool => $i['status'] === 'canceled'));
        $overdue = array_values(array_filter($active, fn (array $i): bool => $i['next_due_date'] < $today));
        $upcoming = array_values(array_filter($active, fn (array $i): bool => $i['next_due_date'] >= $today));

        Response::html(View::render('recurring/index', [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'canceled' => $canceled,
            'summary' => $recurringRepo->summaryForHousehold($householdId),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        $this->renderForm('recurring/create', [
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
            header('Location: /recurring/create');
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

        $recurringId = (new RecurringItemRepository())->create($householdId, $userId, $input);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'recurring.created',
            'recurring_item',
            $recurringId,
            $request->ip(),
            ['name' => $input['name'], 'expected_amount' => $input['expected_amount']]
        );

        $_SESSION['_flash_notice'] = "\"{$input['name']}\" added.";
        header('Location: /recurring');
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $recurringId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $recurringRepo = new RecurringItemRepository();
        $item = $recurringRepo->findById($recurringId, $householdId);

        if ($item === null) {
            Response::html('Recurring item not found.', 404);
            return;
        }

        $this->renderForm('recurring/edit', [
            'item' => $item,
            'payments' => (new TransactionRepository())->listForRecurringItem($recurringId, $householdId),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]);

        unset($_SESSION['_flash_error']);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $recurringId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $recurringRepo = new RecurringItemRepository();
        $existing = $recurringRepo->findById($recurringId, $householdId);

        if ($existing === null) {
            Response::html('Recurring item not found.', 404);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($recurringId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /recurring/' . $recurringId . '/edit');
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

        $recurringRepo->update($recurringId, $householdId, $input);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'recurring.updated',
            'recurring_item',
            $recurringId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Recurring item updated.';
        header('Location: /recurring');
    }

    public function showMarkPaidForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $recurringId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $item = (new RecurringItemRepository())->findById($recurringId, $householdId);

        if ($item === null || $item['status'] !== 'active') {
            Response::html('Recurring item not found.', 404);
            return;
        }

        Response::html(View::render('recurring/pay', [
            'item' => $item,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_error']);
    }

    public function markPaid(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $recurringId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $recurringRepo = new RecurringItemRepository();
        $item = $recurringRepo->findById($recurringId, $householdId);

        if ($item === null || $item['status'] !== 'active') {
            Response::html('Recurring item not found.', 404);
            return;
        }

        $redirectBack = function (string $message) use ($recurringId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /recurring/' . $recurringId . '/pay');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $paidDate = trim($request->post('paid_date'));
        $rawAmount = trim($request->post('amount'));

        if ($paidDate === '' || \DateTime::createFromFormat('Y-m-d', $paidDate) === false) {
            $redirectBack('Please enter a valid date.');
            return;
        }

        if (!MoneyInput::isValid($rawAmount) || bccomp($rawAmount, '0', 2) <= 0) {
            $redirectBack('Please enter a valid amount greater than zero.');
            return;
        }

        $amount = MoneyInput::normalize($rawAmount);
        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById((int) $item['account_id'], $householdId);

        if ($account === null) {
            $redirectBack('That account is no longer available.');
            return;
        }

        $signedAmount = $item['recurring_type'] === 'expense' ? bcmul($amount, '-1', 2) : $amount;

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo = new TransactionRepository();

            $transactionId = $transactionRepo->create($householdId, $userId, [
                'account_id' => $item['account_id'],
                'category_id' => $item['category_id'],
                'transaction_type' => $item['recurring_type'],
                'transaction_date' => $paidDate,
                'payee' => $item['name'],
                'notes' => 'Recurring: ' . $item['name'],
                'exclude_from_budget' => false,
                'exclude_from_reports' => false,
                'signed_amount' => $signedAmount,
            ]);

            $transactionRepo->linkRecurringItem($transactionId, $householdId, $recurringId);

            (new RuleMatchingService())->applyToTransaction($householdId, $transactionId, [
                'payee' => $item['name'],
                'notes' => 'Recurring: ' . $item['name'],
                'amount' => $signedAmount,
                'account_id' => (int) $item['account_id'],
                'transaction_type' => $item['recurring_type'],
            ]);

            $newBalance = AccountRepository::applyDelta($account['current_balance'], $signedAmount, $account['account_type']);
            (new AccountBalanceHistoryRepository())->record(
                (int) $item['account_id'],
                $userId,
                $account['current_balance'],
                $newBalance,
                'Recurring: ' . $item['name']
            );
            $accountRepo->updateBalance((int) $item['account_id'], $householdId, $newBalance);

            $nextDueDate = RecurringItemRepository::advanceDueDate($item['next_due_date'], $item['frequency']);
            $recurringRepo->markPaid($recurringId, $householdId, $nextDueDate, $amount);

            $auditRepo = new AuditLogRepository();
            $auditRepo->log(
                $userId,
                $householdId,
                'recurring.marked_paid',
                'recurring_item',
                $recurringId,
                $request->ip(),
                ['amount' => $amount, 'transaction_id' => $transactionId, 'next_due_date' => $nextDueDate]
            );

            if (bccomp($amount, $item['expected_amount'], 2) !== 0) {
                $auditRepo->log(
                    $userId,
                    $householdId,
                    'recurring.price_changed',
                    'recurring_item',
                    $recurringId,
                    $request->ip(),
                    ['previous_amount' => $item['expected_amount'], 'new_amount' => $amount]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong recording that payment. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = "Marked \"{$item['name']}\" paid.";
        header('Location: /recurring');
    }

    public function cancel(Request $request): void
    {
        $this->changeStatus($request, 'canceled');
    }

    public function reactivate(Request $request): void
    {
        $this->changeStatus($request, 'active');
    }

    private function changeStatus(Request $request, string $status): void
    {
        AuthMiddleware::requireAuth();

        $recurringId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $recurringRepo = new RecurringItemRepository();
        $item = $recurringRepo->findById($recurringId, $householdId);

        if ($item === null) {
            Response::html('Recurring item not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /recurring');
            return;
        }

        $recurringRepo->setStatus($recurringId, $householdId, $status);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            $status === 'canceled' ? 'recurring.canceled' : 'recurring.reactivated',
            'recurring_item',
            $recurringId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = $status === 'canceled' ? 'Recurring item canceled.' : 'Recurring item reactivated.';
        header('Location: /recurring');
    }

    private function renderForm(string $view, array $extra): void
    {
        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render($view, [
            ...$extra,
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'frequencies' => self::FREQUENCIES,
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'account_id' => $request->post('account_id'),
            'category_id' => $request->post('category_id') !== '' ? $request->post('category_id') : null,
            'name' => trim($request->post('name')),
            'recurring_type' => $request->post('recurring_type'),
            'expected_amount' => trim($request->post('expected_amount')),
            'frequency' => $request->post('frequency'),
            'next_due_date' => $request->post('next_due_date'),
            'auto_pay' => $request->post('auto_pay') === '1',
            'notes' => trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
        ];
    }

    private function validate(array $input, int $householdId): ?string
    {
        if ($input['name'] === '') {
            return 'Name is required.';
        }

        if (!in_array($input['recurring_type'], ['income', 'expense'], true)) {
            return 'Please choose a valid type.';
        }

        if (in_array($input['account_id'], ['', null], true) || (new AccountRepository())->findById((int) $input['account_id'], $householdId) === null) {
            return 'Please choose a valid account.';
        }

        if ($input['category_id'] !== null) {
            $category = (new CategoryRepository())->findById((int) $input['category_id'], $householdId);
            if ($category === null || $category['type'] !== $input['recurring_type']) {
                return 'Please choose a category matching the type.';
            }
        }

        if (!MoneyInput::isValid($input['expected_amount']) || bccomp($input['expected_amount'], '0', 2) <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }

        if (!in_array($input['frequency'], self::FREQUENCIES, true)) {
            return 'Please choose a valid frequency.';
        }

        if (($input['next_due_date'] ?? '') === '' || \DateTime::createFromFormat('Y-m-d', $input['next_due_date']) === false) {
            return 'Please enter a valid next due date.';
        }

        return null;
    }
}
