<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\DashboardWidgets;
use App\Support\View;

final class DashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $userRepo = new UserRepository();
        $user = $userRepo->findById($userId);

        $householdId = AuthMiddleware::householdId();
        $household = $householdId !== null ? (new HouseholdRepository())->findById($householdId) : null;
        $accountRepo = new AccountRepository();
        $netWorth = $householdId !== null ? $accountRepo->netWorthSummary($householdId) : null;
        $accounts = $householdId !== null ? $accountRepo->listForHousehold($householdId) : [];
        $transactionRepo = new TransactionRepository();
        $recentTransactions = $householdId !== null ? $transactionRepo->recentForHousehold($householdId, 5) : [];
        $reviewCounts = $householdId !== null ? $transactionRepo->unreviewedCounts($householdId) : ['total' => 0, 'before_today' => 0];

        $layout = $userRepo->getDashboardLayout($userId);
        $widgetOrder = DashboardWidgets::resolveOrder($layout['order']);
        $hiddenWidgets = DashboardWidgets::filterKnown($layout['hidden']);

        Response::html(View::render('dashboard/index', [
            'user' => $user,
            'household' => $household,
            'role' => $_SESSION['role'] ?? null,
            'netWorth' => $netWorth,
            'accounts' => $accounts,
            'recentTransactions' => $recentTransactions,
            'reviewCounts' => $reviewCounts,
            'widgetOrder' => $widgetOrder,
            'hiddenWidgets' => $hiddenWidgets,
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * AJAX endpoint the drag-and-drop grid calls to persist tile order.
     * Only touches the "order" half of the saved layout — visibility is
     * saved separately by saveVisibility() so each action can't clobber
     * the other's most recent change.
     */
    public function saveLayout(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $decoded = json_decode($request->post('order'), true);

        if (!is_array($decoded) || array_filter($decoded, fn ($v): bool => !is_string($v) || !DashboardWidgets::exists($v)) !== []) {
            Response::json(['error' => 'Invalid layout data.'], 422);
            return;
        }

        (new UserRepository())->updateDashboardOrder((int) AuthMiddleware::userId(), array_values($decoded));

        Response::json(['status' => 'ok']);
    }

    /**
     * AJAX endpoint the Customize modal calls to persist which tiles are
     * hidden. Only touches the "hidden" half of the saved layout.
     */
    public function saveVisibility(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $decoded = json_decode($request->post('hidden'), true);

        if (!is_array($decoded) || array_filter($decoded, fn ($v): bool => !is_string($v) || !DashboardWidgets::exists($v)) !== []) {
            Response::json(['error' => 'Invalid widget data.'], 422);
            return;
        }

        (new UserRepository())->updateDashboardHidden((int) AuthMiddleware::userId(), array_values($decoded));

        Response::json(['status' => 'ok']);
    }
}
