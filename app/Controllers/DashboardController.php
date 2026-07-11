<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\HouseholdRepository;
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

        Response::html(View::render('dashboard/index', [
            'user' => $user,
            'household' => $household,
            'role' => $_SESSION['role'] ?? null,
            'netWorth' => $netWorth,
            'accounts' => $accounts,
            'widgetOrder' => DashboardWidgets::resolveOrder($userRepo->getDashboardLayout($userId)),
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * AJAX endpoint the drag-and-drop grid calls to persist tile order.
     */
    public function saveLayout(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $raw = $request->post('order');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || array_filter($decoded, fn ($v): bool => !is_string($v) || !DashboardWidgets::exists($v)) !== []) {
            Response::json(['error' => 'Invalid layout data.'], 422);
            return;
        }

        (new UserRepository())->updateDashboardLayout((int) AuthMiddleware::userId(), array_values($decoded));

        Response::json(['status' => 'ok']);
    }
}
