<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Repositories\TransactionRuleRepository;
use App\Services\RuleMatchingService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class RuleController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $ruleRepo = new TransactionRuleRepository();

        $rules = [];
        foreach ($ruleRepo->listForHousehold($householdId) as $rule) {
            $rules[] = [
                'rule' => $rule,
                'conditions' => $ruleRepo->listConditions((int) $rule['id']),
                'actions' => $ruleRepo->listActions((int) $rule['id']),
            ];
        }

        $accountNames = array_column((new AccountRepository())->listForHousehold($householdId, true), 'name', 'id');
        $categoryNames = array_column((new CategoryRepository())->listForHousehold($householdId, true), 'name', 'id');
        $tagNames = array_column((new TagRepository())->listForHousehold($householdId), 'name', 'id');

        Response::html(View::render('settings/rules', [
            'rules' => $rules,
            'accountNames' => $accountNames,
            'categoryNames' => $categoryNames,
            'tagNames' => $tagNames,
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_notice'], $_SESSION['_flash_error']);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        $this->renderForm('settings/rule-form', [
            'rule' => null,
            'conditions' => [],
            'actions' => [],
            'error' => $_SESSION['_flash_error'] ?? null,
        ]);

        unset($_SESSION['_flash_error']);
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $ruleRepo = new TransactionRuleRepository();
        $rule = $ruleRepo->findById($ruleId, $householdId);

        if ($rule === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        $this->renderForm('settings/rule-form', [
            'rule' => $rule,
            'conditions' => $ruleRepo->listConditions($ruleId),
            'actions' => $ruleRepo->listActions($ruleId),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]);

        unset($_SESSION['_flash_error']);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $this->saveRule($request, $householdId, $userId, null);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        if ((new TransactionRuleRepository())->findById($ruleId, $householdId) === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        $this->saveRule($request, $householdId, $userId, $ruleId);
    }

    private function saveRule(Request $request, int $householdId, int $userId, ?int $ruleId): void
    {
        $redirectTarget = $ruleId === null ? '/settings/rules/create' : '/settings/rules/' . $ruleId . '/edit';

        $redirectBack = function (string $message) use ($redirectTarget): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: ' . $redirectTarget);
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $name = trim($request->post('name'));
        if ($name === '') {
            $redirectBack('Please name this rule.');
            return;
        }

        $conditions = $this->buildConditions($request, $householdId, $error);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }
        if ($conditions === []) {
            $redirectBack('Please set at least one condition.');
            return;
        }

        $actions = $this->buildActions($request, $householdId, $error);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }
        if ($actions === []) {
            $redirectBack('Please choose at least one action.');
            return;
        }

        $ruleRepo = new TransactionRuleRepository();
        $auditRepo = new AuditLogRepository();

        if ($ruleId === null) {
            $ruleId = $ruleRepo->create($householdId, $userId, $name);
            $auditRepo->log($userId, $householdId, 'rule.created', 'transaction_rule', $ruleId, $request->ip(), ['name' => $name]);
        } else {
            $ruleRepo->rename($ruleId, $householdId, $name);
            $auditRepo->log($userId, $householdId, 'rule.updated', 'transaction_rule', $ruleId, $request->ip());
        }

        $ruleRepo->replaceConditions($ruleId, $conditions);
        $ruleRepo->replaceActions($ruleId, $actions);

        $_SESSION['_flash_notice'] = 'Rule saved. It will apply automatically to new transactions from now on.';
        header('Location: /settings/rules');
    }

    public function toggleActive(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $ruleRepo = new TransactionRuleRepository();
        $rule = $ruleRepo->findById($ruleId, $householdId);

        if ($rule === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/rules');
            return;
        }

        $ruleRepo->setActive($ruleId, $householdId, (int) $rule['is_active'] !== 1);

        header('Location: /settings/rules');
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();
        $ruleRepo = new TransactionRuleRepository();
        $rule = $ruleRepo->findById($ruleId, $householdId);

        if ($rule === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/rules');
            return;
        }

        $ruleRepo->delete($ruleId, $householdId);

        (new AuditLogRepository())->log($userId, $householdId, 'rule.deleted', 'transaction_rule', $ruleId, $request->ip(), ['name' => $rule['name']]);

        $_SESSION['_flash_notice'] = 'Rule deleted.';
        header('Location: /settings/rules');
    }

    /**
     * The preview step CLAUDE.md requires before a rule can touch
     * transaction history: shows how many existing transactions currently
     * match, with nothing applied yet.
     */
    public function previewApply(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $ruleRepo = new TransactionRuleRepository();
        $rule = $ruleRepo->findById($ruleId, $householdId);

        if ($rule === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        $conditions = $ruleRepo->listConditions($ruleId);
        $count = (new RuleMatchingService())->previewCount($householdId, $conditions);

        Response::html(View::render('settings/rule-apply', [
            'rule' => $rule,
            'count' => $count,
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function applyToExisting(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $ruleId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();
        $ruleRepo = new TransactionRuleRepository();
        $rule = $ruleRepo->findById($ruleId, $householdId);

        if ($rule === null) {
            Response::html('Rule not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/rules/' . $ruleId . '/apply');
            return;
        }

        $conditions = $ruleRepo->listConditions($ruleId);
        $actions = $ruleRepo->listActions($ruleId);

        $touched = (new RuleMatchingService())->applyToExisting($householdId, $conditions, $actions);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'rule.applied_retroactively',
            'transaction_rule',
            $ruleId,
            $request->ip(),
            ['name' => $rule['name'], 'affected' => $touched]
        );

        $_SESSION['_flash_notice'] = 'Applied "' . $rule['name'] . '" to ' . $touched . ' existing transaction' . ($touched === 1 ? '' : 's') . '.';
        header('Location: /settings/rules');
    }

    /**
     * @return list<array{field: string, operator: string, value: string}>
     */
    private function buildConditions(Request $request, int $householdId, ?string &$error): array
    {
        $error = null;
        $conditions = [];

        $payeeContains = trim($request->post('payee_contains'));
        if ($payeeContains !== '') {
            $conditions[] = ['field' => 'payee', 'operator' => 'contains', 'value' => $payeeContains];
        }

        $notesContains = trim($request->post('notes_contains'));
        if ($notesContains !== '') {
            $conditions[] = ['field' => 'notes', 'operator' => 'contains', 'value' => $notesContains];
        }

        $amountOperator = $request->post('amount_operator');
        $amountValue = trim($request->post('amount_value'));
        if ($amountOperator !== '') {
            if (!in_array($amountOperator, ['equals', 'greater_than', 'less_than'], true) || $amountValue === '' || !MoneyInput::isValid($amountValue)) {
                $error = 'Please enter a valid amount to compare against.';
                return [];
            }
            $conditions[] = ['field' => 'amount', 'operator' => $amountOperator, 'value' => MoneyInput::normalize($amountValue)];
        }

        $accountId = $request->post('account_id');
        if ($accountId !== '') {
            if ((new AccountRepository())->findById((int) $accountId, $householdId) === null) {
                $error = 'Please choose a valid account.';
                return [];
            }
            $conditions[] = ['field' => 'account_id', 'operator' => 'equals', 'value' => (string) (int) $accountId];
        }

        $transactionType = $request->post('transaction_type');
        if ($transactionType !== '') {
            if (!in_array($transactionType, ['income', 'expense'], true)) {
                $error = 'Please choose a valid transaction type.';
                return [];
            }
            $conditions[] = ['field' => 'transaction_type', 'operator' => 'equals', 'value' => $transactionType];
        }

        return $conditions;
    }

    /**
     * @return list<array{action_type: string, value: string|null}>
     */
    private function buildActions(Request $request, int $householdId, ?string &$error): array
    {
        $error = null;
        $actions = [];

        $categoryId = $request->post('category_id');
        if ($categoryId !== '') {
            if ((new CategoryRepository())->findById((int) $categoryId, $householdId) === null) {
                $error = 'Please choose a valid category.';
                return [];
            }
            $actions[] = ['action_type' => 'set_category', 'value' => (string) (int) $categoryId];
        }

        $payeeRename = trim($request->post('payee_rename'));
        if ($payeeRename !== '') {
            $actions[] = ['action_type' => 'set_payee', 'value' => $payeeRename];
        }

        $tagRepo = new TagRepository();
        foreach ($request->postIntList('tag_ids') as $tagId) {
            if ($tagRepo->findById($tagId, $householdId) === null) {
                $error = 'Please choose valid tags.';
                return [];
            }
            $actions[] = ['action_type' => 'add_tag', 'value' => (string) $tagId];
        }

        if ($request->post('mark_reviewed') === '1') {
            $actions[] = ['action_type' => 'mark_reviewed', 'value' => '1'];
        }

        if ($request->post('exclude_from_reports') === '1') {
            $actions[] = ['action_type' => 'exclude_from_reports', 'value' => '1'];
        }

        return $actions;
    }

    private function renderForm(string $view, array $extra): void
    {
        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render($view, [
            ...$extra,
            'accounts' => (new AccountRepository())->listForHousehold($householdId),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'tags' => (new TagRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
        ]));
    }
}
