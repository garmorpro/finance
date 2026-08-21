<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Services\ReportingService;
use App\Support\Csrf;
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
            'csrfToken' => Csrf::token(),
        ]));
    }
}
