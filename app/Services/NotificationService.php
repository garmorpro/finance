<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BudgetRepository;
use App\Repositories\GoalRepository;
use App\Repositories\RecurringItemRepository;
use App\Repositories\UserNotificationPreferenceRepository;

/**
 * In-app notifications only — no email/push delivery exists in this app.
 * Every notification here is a live read of a condition that already has
 * its own dedicated page (budgets, recurring, goals); this service just
 * aggregates "is anything worth surfacing right now" and lets each user
 * opt out of a category. Nothing is persisted as a notification record,
 * so there's no read/unread or dismiss state to manage — the badge
 * always reflects current reality.
 */
final class NotificationService
{
    public const TYPES = [
        'budget_over' => 'Categories over budget',
        'bills_due' => 'Bills overdue',
        'goal_milestones' => 'Goals nearing their target',
    ];

    private UserNotificationPreferenceRepository $preferenceRepo;

    public function __construct()
    {
        $this->preferenceRepo = new UserNotificationPreferenceRepository();
    }

    /**
     * @return array<string, bool> every known type, defaulting to enabled
     */
    public function preferencesForUser(int $userId): array
    {
        $overrides = $this->preferenceRepo->listForUser($userId);

        $result = [];
        foreach (self::TYPES as $type => $label) {
            $result[$type] = $overrides[$type] ?? true;
        }

        return $result;
    }

    public function setPreference(int $userId, string $type, bool $enabled): void
    {
        if (!array_key_exists($type, self::TYPES)) {
            return;
        }

        $this->preferenceRepo->setEnabled($userId, $type, $enabled);
    }

    /**
     * @return list<array{type: string, message: string, count: int, href: string}>
     */
    public function activeNotifications(int $householdId, int $userId): array
    {
        $prefs = $this->preferencesForUser($userId);
        $items = [];

        if ($prefs['budget_over']) {
            $count = $this->overBudgetCategoryCount($householdId);
            if ($count > 0) {
                $items[] = [
                    'type' => 'budget_over',
                    'message' => $count . ' categor' . ($count === 1 ? 'y is' : 'ies are') . ' over budget this month',
                    'count' => $count,
                    'href' => '/budgets',
                ];
            }
        }

        if ($prefs['bills_due']) {
            $count = (new RecurringItemRepository())->summaryForHousehold($householdId)['overdue'];
            if ($count > 0) {
                $items[] = [
                    'type' => 'bills_due',
                    'message' => $count . ' bill' . ($count === 1 ? '' : 's') . ' overdue',
                    'count' => $count,
                    'href' => '/recurring',
                ];
            }
        }

        if ($prefs['goal_milestones']) {
            $count = $this->goalsNearTargetCount($householdId);
            if ($count > 0) {
                $items[] = [
                    'type' => 'goal_milestones',
                    'message' => $count . ' goal' . ($count === 1 ? ' is' : 's are') . ' 90%+ funded',
                    'count' => $count,
                    'href' => '/goals',
                ];
            }
        }

        return $items;
    }

    public function badgeCount(int $householdId, int $userId): int
    {
        $total = 0;
        foreach ($this->activeNotifications($householdId, $userId) as $item) {
            $total += $item['count'];
        }

        return $total;
    }

    private function overBudgetCategoryCount(int $householdId): int
    {
        $budgetRepo = new BudgetRepository();
        $periodMonth = gmdate('Y-m');
        $monthStart = gmdate('Y-m-01');
        $monthEndExclusive = date('Y-m-d', strtotime($monthStart . ' +1 month'));

        $budget = $budgetRepo->findForMonth($householdId, $periodMonth);
        if ($budget === null) {
            return 0;
        }

        $actual = $budgetRepo->actualByCategory($householdId, 'expense', $monthStart, $monthEndExclusive);

        $count = 0;
        foreach ($budgetRepo->listItemsForBudget((int) $budget['id']) as $item) {
            if ($item['category_type'] !== 'expense') {
                continue;
            }

            $spent = $actual[(int) $item['category_id']] ?? '0.00';
            if (bccomp($spent, $item['planned_amount'], 2) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * "Milestone" here means 90%+ funded and not yet marked complete — this
     * app doesn't persist which milestones a goal has already crossed, so
     * it surfaces current proximity to the target rather than pretending
     * to detect a specific 25/50/75% crossing event.
     */
    private function goalsNearTargetCount(int $householdId): int
    {
        $goalService = new GoalService();

        $count = 0;
        foreach ((new GoalRepository())->listForHousehold($householdId) as $goal) {
            if ($goal['status'] !== 'active') {
                continue;
            }

            if ($goalService->progressPercent($goal['current_amount'], $goal['target_amount']) >= 90.0) {
                $count++;
            }
        }

        return $count;
    }
}
