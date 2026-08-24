<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryGroupRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\GoalRepository;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class BudgetController
{
    // Owner/Administrator configure budgets, per CLAUDE.md's role definitions.
    // Members and Viewers can still view the budget, just not edit it.
    private const MANAGE_ROLES = ['owner', 'administrator'];

    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->query('month'));
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $budgetRepo = new BudgetRepository();
        ['incomeSections' => $incomeSections, 'expenseSections' => $expenseSections] = $this->loadSections($budgetRepo, $householdId, $periodMonth);

        $expenseTotals = $this->sectionTotals($expenseSections);
        $incomeTotals = $this->sectionTotals($incomeSections);

        $goalRepo = new GoalRepository();
        $plannedGoalContributions = '0.00';
        foreach ($goalRepo->listForHousehold($householdId) as $goal) {
            if ($goal['status'] === 'active' && $goal['planned_monthly_contribution'] !== null) {
                $plannedGoalContributions = bcadd($plannedGoalContributions, $goal['planned_monthly_contribution'], 2);
            }
        }
        $actualGoalContributions = $goalRepo->totalContributionsForRange($householdId, $periodMonth, $monthEnd);

        // "Left to budget" mirrors the common envelope-budgeting framing:
        // planned income minus everything already earmarked (expenses and
        // planned goal contributions) — not the same as actual surplus,
        // which is what's really left over regardless of the plan.
        $leftToBudget = bcsub(bcsub($incomeTotals['planned'], $expenseTotals['planned'], 2), $plannedGoalContributions, 2);

        $leftToBudgetTrend = $this->leftToBudgetTrend($budgetRepo, $householdId, $periodMonth, $plannedGoalContributions, $leftToBudget);

        Response::html(View::render('budgets/index', [
            'periodMonth' => $periodMonth,
            'monthLabel' => date('F Y', strtotime($periodMonth)),
            'prevMonth' => date('Y-m', strtotime($periodMonth . ' -1 month')),
            'nextMonth' => date('Y-m', strtotime($periodMonth . ' +1 month')),
            'expenseSections' => $expenseSections,
            'incomeSections' => $incomeSections,
            'totalPlannedExpense' => $expenseTotals['planned'],
            'totalActualExpense' => $expenseTotals['actual'],
            'totalPlannedIncome' => $incomeTotals['planned'],
            'totalActualIncome' => $incomeTotals['actual'],
            'plannedSurplus' => bcsub($incomeTotals['planned'], $expenseTotals['planned'], 2),
            'actualSurplus' => bcsub($incomeTotals['actual'], $expenseTotals['actual'], 2),
            'plannedGoalContributions' => $plannedGoalContributions,
            'actualGoalContributions' => $actualGoalContributions,
            'leftToBudget' => $leftToBudget,
            'leftToBudgetTrend' => $leftToBudgetTrend,
            'canManage' => in_array(AuthMiddleware::role(), self::MANAGE_ROLES, true),
            'hasPreviousBudget' => $budgetRepo->findForMonth($householdId, date('Y-m-d', strtotime($periodMonth . ' -1 month'))) !== null,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    /**
     * A guided, one-category-group-at-a-time walkthrough for planning a
     * month in one sitting — same underlying data and the same
     * /budgets/items autosave endpoint as the main page, just presented
     * as a linear sequence instead of a page of collapsed sections. Each
     * row also gets a trailing 3-month average so an unplanned category
     * shows up with a sensible starting number instead of blank.
     */
    public function showReview(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->query('month'));

        $budgetRepo = new BudgetRepository();
        ['incomeSections' => $incomeSections, 'expenseSections' => $expenseSections] = $this->loadSections($budgetRepo, $householdId, $periodMonth);

        // Steps only ever cover sections that actually have categories in
        // them — an empty group (real but nothing assigned to it yet)
        // would otherwise render as a blank, pointless step.
        $incomeSteps = array_values(array_filter($incomeSections, fn (array $s): bool => $s['rows'] !== []));
        $expenseSteps = array_values(array_filter($expenseSections, fn (array $s): bool => $s['rows'] !== []));

        $withAverages = function (array $steps, string $type) use ($budgetRepo, $householdId, $periodMonth): array {
            foreach ($steps as &$section) {
                foreach ($section['rows'] as &$row) {
                    $trailing = array_values($budgetRepo->actualsByCategoryTrailingMonths(
                        $householdId,
                        (int) $row['category']['id'],
                        $type,
                        $periodMonth,
                        3
                    ));

                    $average = '0.00';
                    foreach ($trailing as $amount) {
                        $average = bcadd($average, $amount, 2);
                    }
                    if ($trailing !== []) {
                        $average = bcdiv($average, (string) count($trailing), 2);
                    }

                    $row['average'] = $average;
                }
                unset($row);
            }
            unset($section);

            return $steps;
        };

        $goalRepo = new GoalRepository();
        $plannedGoalContributions = '0.00';
        foreach ($goalRepo->listForHousehold($householdId) as $goal) {
            if ($goal['status'] === 'active' && $goal['planned_monthly_contribution'] !== null) {
                $plannedGoalContributions = bcadd($plannedGoalContributions, $goal['planned_monthly_contribution'], 2);
            }
        }

        $incomeSteps = $withAverages($incomeSteps, 'income');
        $expenseSteps = $withAverages($expenseSteps, 'expense');

        // A category with no existing plan and no recent spending doesn't
        // need its own moment in the wizard — pulled out of its group's
        // step (which still gets a normal step if anything else in it has
        // real activity) into one shared catch-all group, appended as a
        // single final step below rather than each getting a whole screen
        // to confirm "$0.00".
        $isSkippable = fn (array $row): bool => $row['planned'] === null && bccomp($row['average'], '0.00', 2) === 0;

        $catchAllGroups = [];
        $partition = function (array $steps, string $type) use ($isSkippable, &$catchAllGroups): array {
            $kept = [];
            foreach ($steps as $section) {
                $active = array_values(array_filter($section['rows'], fn (array $r): bool => !$isSkippable($r)));
                $skipped = array_values(array_filter($section['rows'], $isSkippable));

                if ($skipped !== []) {
                    $catchAllGroups[] = [
                        'type' => $type,
                        'groupName' => $section['group']['name'] ?? ($type === 'income' ? 'Income' : 'Ungrouped'),
                        'rows' => $skipped,
                    ];
                }

                if ($active !== []) {
                    $kept[] = ['group' => $section['group'], 'rows' => $active];
                }
            }

            return $kept;
        };

        $incomeSteps = $partition($incomeSteps, 'income');
        $expenseSteps = $partition($expenseSteps, 'expense');

        Response::html(View::render('budgets/review', [
            'periodMonth' => $periodMonth,
            'monthLabel' => date('F Y', strtotime($periodMonth)),
            'incomeSteps' => $incomeSteps,
            'expenseSteps' => $expenseSteps,
            'catchAllGroups' => $catchAllGroups,
            'plannedGoalContributions' => $plannedGoalContributions,
            'hasPreviousBudget' => $budgetRepo->findForMonth($householdId, date('Y-m-d', strtotime($periodMonth . ' -1 month'))) !== null,
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_notice'], $_SESSION['_flash_error']);
    }

    public function saveItem(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->post('period_month'));
        $categoryId = (int) $request->post('category_id');
        $rawAmount = trim($request->post('planned_amount'));
        $isAjax = $request->isAjax();

        // The row inputs autosave via fetch() and expect JSON back; a
        // plain form post (no JS, or JS disabled) still gets the
        // traditional flash-message redirect.
        $respond = function (bool $success, string $message) use ($isAjax, $periodMonth): void {
            if ($isAjax) {
                Response::json(['success' => $success, 'message' => $message], $success ? 200 : 422);
                return;
            }

            $_SESSION[$success ? '_flash_notice' : '_flash_error'] = $message;
            header('Location: /budgets?month=' . substr($periodMonth, 0, 7));
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $respond(false, 'Your session expired. Please try again.');
            return;
        }

        $category = (new CategoryRepository())->findById($categoryId, $householdId);
        if ($category === null || $category['archived_at'] !== null) {
            $respond(false, 'Please choose a valid category.');
            return;
        }

        $budgetRepo = new BudgetRepository();

        // An empty field clears the line for this month rather than
        // requiring a separate delete button per row.
        if ($rawAmount === '') {
            $budget = $budgetRepo->findForMonth($householdId, $periodMonth);
            if ($budget !== null) {
                $budgetRepo->deleteItem((int) $budget['id'], $categoryId);
            }

            $respond(true, 'Budget line cleared.');
            return;
        }

        if (!MoneyInput::isValid($rawAmount) || bccomp($rawAmount, '0', 2) < 0) {
            $respond(false, 'Please enter a valid amount of zero or more.');
            return;
        }

        $normalizedAmount = MoneyInput::normalize($rawAmount);

        $budget = $budgetRepo->findOrCreateForMonth($householdId, $periodMonth);
        $budgetRepo->upsertItem((int) $budget['id'], $categoryId, $normalizedAmount);

        // "Apply to all future months" sets this as the household's
        // standing plan for the category from this month onward — it
        // never retroactively affects a month before this one.
        if ($request->post('apply_to_future') === '1') {
            $budgetRepo->upsertDefault($householdId, $categoryId, $normalizedAmount, $periodMonth);
        }

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'budget.item_set',
            'budget',
            (int) $budget['id'],
            $request->ip(),
            ['category_id' => $categoryId, 'planned_amount' => $normalizedAmount]
        );

        $respond(true, 'Budget updated.');
    }

    public function categoryHistory(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $categoryId = (int) $request->query('category_id');
        $periodMonth = $this->resolveMonth($request->query('month'));

        $category = (new CategoryRepository())->findById($categoryId, $householdId);
        if ($category === null) {
            Response::notFound();
            return;
        }

        $monthsBack = 6;
        $byMonth = (new BudgetRepository())->actualsByCategoryTrailingMonths(
            $householdId,
            $categoryId,
            $category['type'],
            $periodMonth,
            $monthsBack
        );

        $amounts = array_values($byMonth);
        $lastMonth = $amounts !== [] ? end($amounts) : '0.00';
        $average = '0.00';
        foreach ($amounts as $amount) {
            $average = bcadd($average, $amount, 2);
        }
        if ($amounts !== []) {
            $average = bcdiv($average, (string) count($amounts), 2);
        }

        Response::json([
            'success' => true,
            'lastMonth' => $lastMonth,
            'average' => $average,
        ]);
    }

    public function copyPrevious(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->post('period_month'));
        $monthQuery = substr($periodMonth, 0, 7);

        // The review flow offers the same "copy last month" action; rather
        // than accept an arbitrary redirect target (an open-redirect risk),
        // this is a plain boolean flag choosing between the only two valid
        // destinations.
        $returnPath = $request->post('from_review') === '1' ? '/budgets/review' : '/budgets';

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: ' . $returnPath . '?month=' . $monthQuery);
            return;
        }

        $budgetRepo = new BudgetRepository();
        $previousMonth = date('Y-m-d', strtotime($periodMonth . ' -1 month'));
        $previousBudget = $budgetRepo->findForMonth($householdId, $previousMonth);

        if ($previousBudget === null) {
            $_SESSION['_flash_error'] = 'No budget found for the previous month to copy.';
            header('Location: ' . $returnPath . '?month=' . $monthQuery);
            return;
        }

        $currentBudget = $budgetRepo->findOrCreateForMonth($householdId, $periodMonth);
        $copied = $budgetRepo->copyItemsFrom((int) $previousBudget['id'], (int) $currentBudget['id']);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'budget.copied_previous',
            'budget',
            (int) $currentBudget['id'],
            $request->ip(),
            ['copied' => $copied]
        );

        $_SESSION['_flash_notice'] = $copied > 0
            ? 'Copied ' . $copied . ' budget line' . ($copied === 1 ? '' : 's') . ' from last month.'
            : 'Nothing new to copy — every category already has a budget line this month.';
        header('Location: ' . $returnPath . '?month=' . $monthQuery);
    }

    /**
     * The shared fetch-and-group pipeline behind both the main Budgets
     * page and the guided review flow: this month's saved lines (layered
     * with standing "apply to future months" defaults), this month's
     * actual activity, and every active category bucketed into its
     * category_group. Both callers build their own totals/steps from the
     * sections this returns.
     *
     * @return array{incomeSections: list<array{group: array|null, rows: list<array>}>, expenseSections: list<array{group: array|null, rows: list<array>}>}
     */
    private function loadSections(BudgetRepository $budgetRepo, int $householdId, string $periodMonth): array
    {
        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

        $budget = $budgetRepo->findForMonth($householdId, $periodMonth);
        $items = $budget !== null ? $budgetRepo->listItemsForBudget((int) $budget['id']) : [];

        $itemsByCategory = [];
        foreach ($items as $item) {
            $itemsByCategory[(int) $item['category_id']] = $item;
        }

        // Standing "apply to all future months" defaults fill in any
        // category without an explicit line for this month — an explicit
        // budget_items row always wins over a default, and this never
        // writes anything; it only affects what's displayed.
        foreach ($budgetRepo->defaultsForHousehold($householdId, $periodMonth) as $categoryId => $defaultAmount) {
            if (!isset($itemsByCategory[$categoryId])) {
                $itemsByCategory[$categoryId] = ['category_id' => $categoryId, 'planned_amount' => $defaultAmount];
            }
        }

        $actualExpenseByCategory = $budgetRepo->actualByCategory($householdId, 'expense', $periodMonth, $monthEnd);
        $actualIncomeByCategory = $budgetRepo->actualByCategory($householdId, 'income', $periodMonth, $monthEnd);

        // Non-archived categories only, but every category of the type
        // shows up here (not just budgeted ones) — the budget page doubles
        // as a full view of how income/expenses are organized into sections.
        $allCategories = (new CategoryRepository())->listForHousehold($householdId, true);
        $activeCategories = array_values(array_filter($allCategories, fn (array $c): bool => $c['archived_at'] === null));
        $expenseCategories = array_values(array_filter($activeCategories, fn (array $c): bool => $c['type'] === 'expense'));
        $incomeCategories = array_values(array_filter($activeCategories, fn (array $c): bool => $c['type'] === 'income'));

        $groupRepo = new CategoryGroupRepository();
        $expenseGroups = $groupRepo->listForHousehold($householdId, 'expense');
        $incomeGroups = $groupRepo->listForHousehold($householdId, 'income');

        return [
            'incomeSections' => $this->buildSections($incomeCategories, $incomeGroups, $itemsByCategory, $actualIncomeByCategory),
            'expenseSections' => $this->buildSections($expenseCategories, $expenseGroups, $itemsByCategory, $actualExpenseByCategory),
        ];
    }

    /**
     * Buckets categories of one type into their sections (in group sort
     * order), with an "Ungrouped" bucket last for anything with no group
     * (or whose group was deleted/mismatched — categories.group_id is
     * kept in sync at the DB level via ON DELETE SET NULL, so this is
     * just a defensive fallback, not a real-world path).
     *
     * @param array<int, array> $itemsByCategory
     * @param array<int, string> $actualByCategory
     * @return list<array{group: array|null, rows: list<array>}>
     */
    private function buildSections(array $categories, array $groups, array $itemsByCategory, array $actualByCategory): array
    {
        $sections = [];
        foreach ($groups as $group) {
            $sections[(int) $group['id']] = ['group' => $group, 'rows' => []];
        }
        $sections[0] = ['group' => null, 'rows' => []];

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $groupId = $category['group_id'] !== null ? (int) $category['group_id'] : 0;
            if (!isset($sections[$groupId])) {
                $groupId = 0;
            }

            $planned = $itemsByCategory[$categoryId]['planned_amount'] ?? null;
            $actual = $actualByCategory[$categoryId] ?? '0.00';

            $sections[$groupId]['rows'][] = [
                'category' => $category,
                'planned' => $planned,
                'actual' => $actual,
                'remaining' => $planned !== null ? bcsub($planned, $actual, 2) : null,
            ];
        }

        // The synthetic "Ungrouped" bucket (key 0) only shows up if it
        // actually has categories in it; real sections stay visible even
        // empty, so a freshly created section isn't invisible until
        // something gets assigned to it.
        return array_values(array_filter(
            $sections,
            fn (array $section, int $key): bool => $key !== 0 || $section['rows'] !== [],
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @param list<array{group: array|null, rows: list<array>}> $sections
     * @return array{planned: string, actual: string}
     */
    private function sectionTotals(array $sections): array
    {
        $planned = '0.00';
        $actual = '0.00';

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                if ($row['planned'] !== null) {
                    $planned = bcadd($planned, $row['planned'], 2);
                }
                $actual = bcadd($actual, $row['actual'], 2);
            }
        }

        return ['planned' => $planned, 'actual' => $actual];
    }

    /**
     * A 6-month trailing "Left to Budget" series for the Overview hero's
     * sparkline — the last point is always exactly $currentLeftToBudget
     * (already computed with the current month's standing-default
     * layering above), so the sparkline's endpoint never disagrees with
     * the big number next to it. Earlier months use only what was
     * explicitly planned that month (budget_items rows) — standing
     * defaults are a display convenience for the current/future months,
     * not a claim about what a past month's plan actually was. A month
     * with no budget row at all shows as $0.00 rather than fabricating a
     * number: nothing was planned, so there's nothing to report.
     *
     * @return list<array{label: string, value: string}>
     */
    private function leftToBudgetTrend(
        BudgetRepository $budgetRepo,
        int $householdId,
        string $currentMonth,
        string $plannedGoalContributions,
        string $currentLeftToBudget,
        int $monthsBack = 5
    ): array {
        $trend = [];

        for ($i = $monthsBack; $i >= 1; $i--) {
            $month = date('Y-m-01', strtotime($currentMonth . " -{$i} months"));
            $budget = $budgetRepo->findForMonth($householdId, $month);

            $value = '0.00';
            if ($budget !== null) {
                $incomePlanned = '0.00';
                $expensePlanned = '0.00';
                foreach ($budgetRepo->listItemsForBudget((int) $budget['id']) as $item) {
                    if ($item['planned_amount'] === null) {
                        continue;
                    }
                    if ($item['category_type'] === 'income') {
                        $incomePlanned = bcadd($incomePlanned, $item['planned_amount'], 2);
                    } else {
                        $expensePlanned = bcadd($expensePlanned, $item['planned_amount'], 2);
                    }
                }
                $value = bcsub(bcsub($incomePlanned, $expensePlanned, 2), $plannedGoalContributions, 2);
            }

            $trend[] = ['label' => date('M', strtotime($month)), 'value' => $value];
        }

        $trend[] = ['label' => date('M', strtotime($currentMonth)), 'value' => $currentLeftToBudget];

        return $trend;
    }

    /**
     * Normalizes a "YYYY-MM" query/post value to a "YYYY-MM-01" DATE
     * string, defaulting to the current month for anything missing or
     * malformed rather than trusting client-supplied dates outright.
     */
    private function resolveMonth(?string $value): string
    {
        if ($value !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return $value . '-01';
        }

        if ($value !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/', $value) === 1) {
            return substr($value, 0, 7) . '-01';
        }

        return gmdate('Y-m-01');
    }
}
