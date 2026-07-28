<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Services\ReportingService;
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

        $reportingService = new ReportingService();
        $netWorthTrend = $householdId !== null ? $reportingService->netWorthTrend($householdId) : [];
        $allocationSummary = $householdId !== null ? $reportingService->monthlyAllocationSummary($householdId, gmdate('Y-m-01')) : null;
        $runwayMonths = $householdId !== null ? $reportingService->runwayMonths($householdId) : null;
        $thisMonthCashFlow = $householdId !== null ? array_slice($reportingService->cashFlow($householdId, 1), -1)[0] ?? null : null;
        $incomeByCategory = $householdId !== null ? $reportingService->incomeByCategory($householdId, gmdate('Y-m-01')) : [];
        $topExpenseCategories = $householdId !== null ? array_slice($reportingService->spendingByCategory($householdId, gmdate('Y-m-01')), 0, 5) : [];

        $layout = $userRepo->getDashboardLayout($userId);
        $widgetOrder = DashboardWidgets::resolveOrder($layout['order']);
        $hiddenWidgets = DashboardWidgets::filterKnown($layout['hidden']);
        $wideWidgets = DashboardWidgets::resolveWide($layout['wide']);

        Response::html(View::render('dashboard/index', [
            'user' => $user,
            'household' => $household,
            'role' => $_SESSION['role'] ?? null,
            'netWorth' => $netWorth,
            'netWorthTrend' => $netWorthTrend,
            'allocationSummary' => $allocationSummary,
            'runwayMonths' => $runwayMonths,
            'thisMonthCashFlow' => $thisMonthCashFlow,
            'incomeByCategory' => $incomeByCategory,
            'topExpenseCategories' => $topExpenseCategories,
            'widgetOrder' => $widgetOrder,
            'hiddenWidgets' => $hiddenWidgets,
            'wideWidgets' => $wideWidgets,
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

    /**
     * AJAX endpoint the per-tile resize toggle calls. Saves the complete
     * current set of full-width tiles (not a diff), same convention as
     * saveVisibility().
     */
    public function saveWidth(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $decoded = json_decode($request->post('wide'), true);

        if (!is_array($decoded) || array_filter($decoded, fn ($v): bool => !is_string($v) || !DashboardWidgets::exists($v)) !== []) {
            Response::json(['error' => 'Invalid layout data.'], 422);
            return;
        }

        (new UserRepository())->updateDashboardWide((int) AuthMiddleware::userId(), array_values($decoded));

        Response::json(['status' => 'ok']);
    }
}
