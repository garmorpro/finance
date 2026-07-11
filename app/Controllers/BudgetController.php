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
        $budget = $budgetRepo->findForMonth($householdId, $periodMonth);
        $items = $budget !== null ? $budgetRepo->listItemsForBudget((int) $budget['id']) : [];

        $itemsByCategory = [];
        foreach ($items as $item) {
            $itemsByCategory[(int) $item['category_id']] = $item;
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

        $expenseSections = $this->buildSections($expenseCategories, $expenseGroups, $itemsByCategory, $actualExpenseByCategory);
        $incomeSections = $this->buildSections($incomeCategories, $incomeGroups, $itemsByCategory, $actualIncomeByCategory);

        $expenseTotals = $this->sectionTotals($expenseSections);
        $incomeTotals = $this->sectionTotals($incomeSections);

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
            'canManage' => in_array(AuthMiddleware::role(), self::MANAGE_ROLES, true),
            'hasPreviousBudget' => $budgetRepo->findForMonth($householdId, date('Y-m-d', strtotime($periodMonth . ' -1 month'))) !== null,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function saveItem(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->post('period_month'));
        $categoryId = (int) $request->post('category_id');
        $rawAmount = trim($request->post('planned_amount'));

        $redirectBack = function (string $message) use ($periodMonth): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /budgets?month=' . substr($periodMonth, 0, 7));
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $category = (new CategoryRepository())->findById($categoryId, $householdId);
        if ($category === null || $category['archived_at'] !== null) {
            $redirectBack('Please choose a valid category.');
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

            $_SESSION['_flash_notice'] = 'Budget line cleared.';
            header('Location: /budgets?month=' . substr($periodMonth, 0, 7));
            return;
        }

        if (!MoneyInput::isValid($rawAmount) || bccomp($rawAmount, '0', 2) < 0) {
            $redirectBack('Please enter a valid amount of zero or more.');
            return;
        }

        $budget = $budgetRepo->findOrCreateForMonth($householdId, $periodMonth);
        $budgetRepo->upsertItem((int) $budget['id'], $categoryId, MoneyInput::normalize($rawAmount));

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'budget.item_set',
            'budget',
            (int) $budget['id'],
            $request->ip(),
            ['category_id' => $categoryId, 'planned_amount' => MoneyInput::normalize($rawAmount)]
        );

        $_SESSION['_flash_notice'] = 'Budget updated.';
        header('Location: /budgets?month=' . substr($periodMonth, 0, 7));
    }

    public function copyPrevious(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = (int) AuthMiddleware::householdId();
        $periodMonth = $this->resolveMonth($request->post('period_month'));
        $monthQuery = substr($periodMonth, 0, 7);

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /budgets?month=' . $monthQuery);
            return;
        }

        $budgetRepo = new BudgetRepository();
        $previousMonth = date('Y-m-d', strtotime($periodMonth . ' -1 month'));
        $previousBudget = $budgetRepo->findForMonth($householdId, $previousMonth);

        if ($previousBudget === null) {
            $_SESSION['_flash_error'] = 'No budget found for the previous month to copy.';
            header('Location: /budgets?month=' . $monthQuery);
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
        header('Location: /budgets?month=' . $monthQuery);
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
                    $actual = bcadd($actual, $row['actual'], 2);
                }
            }
        }

        return ['planned' => $planned, 'actual' => $actual];
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
