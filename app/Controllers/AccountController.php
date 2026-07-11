<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class AccountController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $accountRepo = new AccountRepository();

        Response::html(View::render('accounts/index', [
            'accounts' => $accountRepo->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        Response::html(View::render('accounts/create', [
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
            'types' => AccountRepository::TYPES,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $input = $this->readAccountInput($request);
        $input['current_balance'] = trim($request->post('current_balance'));

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /accounts/create');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validateAccountInput($input, $input['current_balance']);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo = new AccountRepository();
        $accountId = $accountRepo->create($householdId, $userId, [
            ...$this->normalizeAccountInput($input),
            'current_balance' => MoneyInput::normalize($input['current_balance']),
        ]);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'account.created',
            'account',
            $accountId,
            $request->ip(),
            ['name' => $input['name']]
        );

        $_SESSION['_flash_notice'] = "Account \"{$input['name']}\" created.";
        header('Location: /accounts');
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $accountId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $account = (new AccountRepository())->findById($accountId, $householdId);

        if ($account === null) {
            Response::html('Account not found.', 404);
            return;
        }

        Response::html(View::render('accounts/edit', [
            'account' => $account,
            'history' => (new AccountBalanceHistoryRepository())->listForAccount($accountId, $householdId),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'types' => AccountRepository::TYPES,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $accountId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById($accountId, $householdId);

        if ($account === null) {
            Response::html('Account not found.', 404);
            return;
        }

        $input = $this->readAccountInput($request);

        $redirectBack = function (string $message) use ($accountId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /accounts/' . $accountId . '/edit');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validateAccountInput($input, null);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo->update($accountId, $householdId, $this->normalizeAccountInput($input));

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'account.updated',
            'account',
            $accountId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Account updated.';
        header('Location: /accounts/' . $accountId . '/edit');
    }

    public function updateBalance(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $accountId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById($accountId, $householdId);

        $redirectBack = function (string $message) use ($accountId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /accounts/' . $accountId . '/edit');
        };

        if ($account === null) {
            Response::html('Account not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $rawBalance = $request->post('new_balance');

        if (!MoneyInput::isValid($rawBalance)) {
            $redirectBack('Please enter a valid balance amount.');
            return;
        }

        $newBalance = MoneyInput::normalize($rawBalance);
        $note = trim($request->post('note')) ?: null;

        // Balance changes are never silently overwritten — every change
        // is recorded in account_balance_history with who/when/why.
        (new AccountBalanceHistoryRepository())->record(
            $accountId,
            $userId,
            $account['current_balance'],
            $newBalance,
            $note
        );

        $accountRepo->updateBalance($accountId, $householdId, $newBalance);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'account.balance_adjusted',
            'account',
            $accountId,
            $request->ip(),
            ['previous_balance' => $account['current_balance'], 'new_balance' => $newBalance]
        );

        $_SESSION['_flash_notice'] = 'Balance updated.';
        header('Location: /accounts/' . $accountId . '/edit');
    }

    public function archive(Request $request): void
    {
        $this->changeStatus($request, 'archived', 'account.archived');
    }

    public function restore(Request $request): void
    {
        $this->changeStatus($request, 'active', 'account.restored');
    }

    private function changeStatus(Request $request, string $status, string $auditAction): void
    {
        AuthMiddleware::requireAuth();

        $accountId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById($accountId, $householdId);

        if ($account === null) {
            Response::html('Account not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /accounts');
            return;
        }

        $accountRepo->setStatus($accountId, $householdId, $status);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            $auditAction,
            'account',
            $accountId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Account updated.';
        header('Location: /accounts');
    }

    /**
     * Reads raw, trimmed strings only — no normalization or type coercion
     * happens here, so validateAccountInput() always sees exactly what the
     * user typed and can reject garbage instead of it being silently
     * coerced to 0.00 by an earlier float cast.
     *
     * @return array<string, string>
     */
    private function readAccountInput(Request $request): array
    {
        return [
            'name' => trim($request->post('name')),
            'institution_name' => trim($request->post('institution_name')),
            'account_type' => $request->post('account_type'),
            'available_balance' => trim($request->post('available_balance')),
            'credit_limit' => trim($request->post('credit_limit')),
            'interest_rate' => trim($request->post('interest_rate')),
            'minimum_payment' => trim($request->post('minimum_payment')),
            'payment_due_day' => trim($request->post('payment_due_day')),
            'original_balance' => trim($request->post('original_balance')),
            'color' => trim($request->post('color')),
            'include_in_net_worth' => $request->post('include_in_net_worth') === '1' ? '1' : '0',
            'include_in_budget' => $request->post('include_in_budget') === '1' ? '1' : '0',
            'notes' => trim($request->post('notes')),
        ];
    }

    private function validateAccountInput(array $input, ?string $rawCurrentBalance): ?string
    {
        if ($input['name'] === '') {
            return 'Account name is required.';
        }

        if (!in_array($input['account_type'], AccountRepository::TYPES, true)) {
            return 'Please choose a valid account type.';
        }

        if ($rawCurrentBalance !== null && !MoneyInput::isValid($rawCurrentBalance)) {
            return 'Please enter a valid current balance.';
        }

        foreach (['available_balance', 'credit_limit', 'minimum_payment', 'original_balance'] as $field) {
            if ($input[$field] !== '' && !MoneyInput::isValid($input[$field])) {
                return 'Please enter a valid amount for ' . str_replace('_', ' ', $field) . '.';
            }
        }

        if ($input['interest_rate'] !== '' && !preg_match('/^\d{1,3}(\.\d{1,3})?$/', $input['interest_rate'])) {
            return 'Please enter a valid interest rate (e.g. 4.5).';
        }

        if ($input['payment_due_day'] !== '') {
            $isDigits = ctype_digit($input['payment_due_day']);
            $day = $isDigits ? (int) $input['payment_due_day'] : 0;

            if (!$isDigits || $day < 1 || $day > 31) {
                return 'Payment due day must be a number between 1 and 31.';
            }
        }

        if ($input['color'] !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'])) {
            return 'Color must be a hex value like #3b82f6.';
        }

        return null;
    }

    /**
     * Converts validated raw strings into DB-ready values. Only called
     * after validateAccountInput() has confirmed everything is valid.
     *
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    private function normalizeAccountInput(array $input): array
    {
        return [
            'name' => $input['name'],
            'institution_name' => $input['institution_name'] !== '' ? $input['institution_name'] : null,
            'account_type' => $input['account_type'],
            'available_balance' => $input['available_balance'] !== '' ? MoneyInput::normalize($input['available_balance']) : null,
            'credit_limit' => $input['credit_limit'] !== '' ? MoneyInput::normalize($input['credit_limit']) : null,
            'interest_rate' => $input['interest_rate'] !== '' ? $input['interest_rate'] : null,
            'minimum_payment' => $input['minimum_payment'] !== '' ? MoneyInput::normalize($input['minimum_payment']) : null,
            'payment_due_day' => $input['payment_due_day'] !== '' ? (int) $input['payment_due_day'] : null,
            'original_balance' => $input['original_balance'] !== '' ? MoneyInput::normalize($input['original_balance']) : null,
            'color' => $input['color'] !== '' ? $input['color'] : null,
            'include_in_net_worth' => $input['include_in_net_worth'] === '1',
            'include_in_budget' => $input['include_in_budget'] === '1',
            'notes' => $input['notes'] !== '' ? $input['notes'] : null,
        ];
    }
}
