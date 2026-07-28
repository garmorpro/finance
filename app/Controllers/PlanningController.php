<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\GoalRepository;
use App\Repositories\RecurringItemRepository;
use App\Services\GoalService;
use App\Support\Csrf;
use App\Support\View;

/**
 * A single "Planning" entry point covering Recurring bills/subscriptions and
 * Goals — two distinct, unchanged features (their own controllers, routes,
 * and full list pages still exist) presented together as one hub with a
 * short preview of each and a link through to the full page.
 */
final class PlanningController
{
    private const PREVIEW_LIMIT = 6;

    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $recurringRepo = new RecurringItemRepository();
        $upcomingRecurring = $recurringRepo->upcomingForHousehold($householdId, self::PREVIEW_LIMIT);
        $recurringSummary = $recurringRepo->summaryForHousehold($householdId);

        $goalService = new GoalService();
        $activeGoals = array_values(array_filter(
            (new GoalRepository())->listForHousehold($householdId),
            fn (array $g): bool => $g['status'] === 'active'
        ));
        $topGoals = array_map(
            fn (array $g): array => [...$g, 'progressPercent' => $goalService->progressPercent($g['current_amount'], $g['target_amount'])],
            array_slice($activeGoals, 0, self::PREVIEW_LIMIT)
        );

        Response::html(View::render('planning/index', [
            'upcomingRecurring' => $upcomingRecurring,
            'recurringSummary' => $recurringSummary,
            'topGoals' => $topGoals,
            'activeGoalCount' => count($activeGoals),
            'csrfToken' => Csrf::token(),
        ]));
    }
}
