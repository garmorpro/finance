<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GoalRepository;
use App\Repositories\HouseholdRepository;
use App\Services\GoalService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class GoalController
{
    private const GOAL_TYPES = [
        'emergency_fund', 'vacation', 'home_project', 'vehicle', 'baby_expenses',
        'debt_payoff', 'investment_target', 'mortgage_payoff', 'general_savings',
    ];

    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $goals = (new GoalRepository())->listForHousehold($householdId);
        $goalService = new GoalService();

        $active = [];
        $completed = [];
        $archived = [];

        foreach ($goals as $goal) {
            $enriched = [
                ...$goal,
                'progressPercent' => $goalService->progressPercent($goal['current_amount'], $goal['target_amount']),
                'estimatedCompletion' => $goalService->estimatedCompletion($goal['current_amount'], $goal['target_amount'], $goal['planned_monthly_contribution']),
            ];

            match ($goal['status']) {
                'completed' => $completed[] = $enriched,
                'archived' => $archived[] = $enriched,
                default => $active[] = $enriched,
            };
        }

        Response::html(View::render('goals/index', [
            'active' => $active,
            'completed' => $completed,
            'archived' => $archived,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        $this->renderForm('goals/create', [
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
            header('Location: /goals/create');
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

        $goalId = (new GoalRepository())->create($householdId, $userId, $input);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'goal.created',
            'goal',
            $goalId,
            $request->ip(),
            ['name' => $input['name'], 'target_amount' => $input['target_amount']]
        );

        $_SESSION['_flash_notice'] = "\"{$input['name']}\" added.";
        header('Location: /goals');
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $goalId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $goalRepo = new GoalRepository();
        $goal = $goalRepo->findById($goalId, $householdId);

        if ($goal === null) {
            Response::html('Goal not found.', 404);
            return;
        }

        $goalService = new GoalService();

        $this->renderForm('goals/edit', [
            'goal' => $goal,
            'contributions' => $goalRepo->listContributions($goalId, $householdId),
            'progressPercent' => $goalService->progressPercent($goal['current_amount'], $goal['target_amount']),
            'estimatedCompletion' => $goalService->estimatedCompletion($goal['current_amount'], $goal['target_amount'], $goal['planned_monthly_contribution']),
            'suggestedMonthlyContribution' => $goalService->suggestedMonthlyContribution($goal['current_amount'], $goal['target_amount'], $goal['target_date']),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]);

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $goalId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $goalRepo = new GoalRepository();
        $existing = $goalRepo->findById($goalId, $householdId);

        if ($existing === null) {
            Response::html('Goal not found.', 404);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($goalId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /goals/' . $goalId . '/edit');
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

        $goalRepo->update($goalId, $householdId, $input);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'goal.updated',
            'goal',
            $goalId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Goal updated.';
        header('Location: /goals/' . $goalId . '/edit');
    }

    public function addContribution(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $goalId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $goalRepo = new GoalRepository();
        $goal = $goalRepo->findById($goalId, $householdId);

        if ($goal === null) {
            Response::html('Goal not found.', 404);
            return;
        }

        $redirectBack = function (string $message) use ($goalId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /goals/' . $goalId . '/edit');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $rawAmount = trim($request->post('amount'));
        $date = trim($request->post('contribution_date'));
        $notes = trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null;

        if (!MoneyInput::isValid($rawAmount) || bccomp($rawAmount, '0', 2) === 0) {
            $redirectBack('Please enter a non-zero amount (use a negative number to record a withdrawal).');
            return;
        }

        if ($date === '' || \DateTime::createFromFormat('Y-m-d', $date) === false) {
            $redirectBack('Please enter a valid date.');
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $contributionId = $goalRepo->addContribution($goalId, $householdId, $userId, MoneyInput::normalize($rawAmount), $date, $notes);

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'goal.contribution_added',
                'goal',
                $goalId,
                $request->ip(),
                ['amount' => MoneyInput::normalize($rawAmount), 'contribution_id' => $contributionId]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong recording that contribution. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Contribution added.';
        header('Location: /goals/' . $goalId . '/edit');
    }

    public function deleteContribution(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $goalId = (int) $request->param('id');
        $contributionId = (int) $request->param('contributionId');
        $householdId = (int) AuthMiddleware::householdId();

        $goalRepo = new GoalRepository();

        if ($goalRepo->findById($goalId, $householdId) === null) {
            Response::html('Goal not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /goals/' . $goalId . '/edit');
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $deleted = $goalRepo->deleteContribution($contributionId, $goalId, $householdId);

            if ($deleted) {
                (new AuditLogRepository())->log(
                    (int) AuthMiddleware::userId(),
                    $householdId,
                    'goal.contribution_deleted',
                    'goal',
                    $goalId,
                    $request->ip(),
                    ['contribution_id' => $contributionId]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['_flash_error'] = 'Something went wrong removing that contribution. Please try again.';
            header('Location: /goals/' . $goalId . '/edit');
            return;
        }

        $_SESSION['_flash_notice'] = $deleted ? 'Contribution removed.' : 'That contribution was already removed.';
        header('Location: /goals/' . $goalId . '/edit');
    }

    public function complete(Request $request): void
    {
        $this->changeStatus($request, 'completed');
    }

    public function archive(Request $request): void
    {
        $this->changeStatus($request, 'archived');
    }

    public function reactivate(Request $request): void
    {
        $this->changeStatus($request, 'active');
    }

    private function changeStatus(Request $request, string $status): void
    {
        AuthMiddleware::requireAuth();

        $goalId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $goalRepo = new GoalRepository();
        $goal = $goalRepo->findById($goalId, $householdId);

        if ($goal === null) {
            Response::html('Goal not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /goals');
            return;
        }

        $goalRepo->setStatus($goalId, $householdId, $status);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'goal.' . $status,
            'goal',
            $goalId,
            $request->ip()
        );

        $labels = ['completed' => 'marked complete', 'archived' => 'archived', 'active' => 'reactivated'];
        $_SESSION['_flash_notice'] = 'Goal ' . $labels[$status] . '.';
        header('Location: /goals');
    }

    private function renderForm(string $view, array $extra): void
    {
        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render($view, [
            ...$extra,
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'members' => (new HouseholdRepository())->listMembers($householdId),
            'goalTypes' => self::GOAL_TYPES,
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'name' => trim($request->post('name')),
            'description' => trim($request->post('description')) !== '' ? trim($request->post('description')) : null,
            'goal_type' => $request->post('goal_type'),
            'target_amount' => trim($request->post('target_amount')),
            'target_date' => trim($request->post('target_date')) !== '' ? trim($request->post('target_date')) : null,
            'planned_monthly_contribution' => trim($request->post('planned_monthly_contribution')) !== '' ? trim($request->post('planned_monthly_contribution')) : null,
            'responsible_user_id' => $request->post('responsible_user_id') !== '' ? $request->post('responsible_user_id') : null,
            'linked_account_id' => $request->post('linked_account_id') !== '' ? $request->post('linked_account_id') : null,
        ];
    }

    private function validate(array $input, int $householdId): ?string
    {
        if ($input['name'] === '') {
            return 'Name is required.';
        }

        if (!in_array($input['goal_type'], self::GOAL_TYPES, true)) {
            return 'Please choose a valid goal type.';
        }

        if (!MoneyInput::isValid($input['target_amount']) || bccomp($input['target_amount'], '0', 2) <= 0) {
            return 'Please enter a target amount greater than zero.';
        }

        if ($input['target_date'] !== null && \DateTime::createFromFormat('Y-m-d', $input['target_date']) === false) {
            return 'Please enter a valid target date.';
        }

        if ($input['planned_monthly_contribution'] !== null
            && (!MoneyInput::isValid($input['planned_monthly_contribution']) || bccomp($input['planned_monthly_contribution'], '0', 2) < 0)
        ) {
            return 'Planned monthly contribution must be a valid amount of zero or more.';
        }

        if ($input['responsible_user_id'] !== null) {
            $members = (new HouseholdRepository())->listMembers($householdId);
            if (!in_array((int) $input['responsible_user_id'], array_map(fn (array $m): int => (int) $m['id'], $members), true)) {
                return 'Please choose a valid household member.';
            }
        }

        if ($input['linked_account_id'] !== null && (new AccountRepository())->findById((int) $input['linked_account_id'], $householdId) === null) {
            return 'Please choose a valid account.';
        }

        return null;
    }
}
