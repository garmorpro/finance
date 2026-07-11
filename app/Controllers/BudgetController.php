<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\BudgetRepository;
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

        $budgetRepo = new BudgetRepository();
        $budget = $budgetRepo->findForMonth($householdId, $periodMonth);
        $items = $budget !== null ? $budgetRepo->listItemsForBudget((int) $budget['id']) : [];
        $itemsByCategory = [];
        foreach ($items as $item) {
            $itemsByCategory[(int) $item['category_id']] = $item;
        }

        $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));
        $actualByCategory = $budgetRepo->actualSpendingByCategory($householdId, $periodMonth, $monthEnd);

        // Includes archived categories so a category archived mid-month
        // still resolves to its real name in the "unbudgeted spending"
        // list below, instead of transactions tagged to it before it was
        // archived falling back to "Unknown category".
        $allCategories = (new CategoryRepository())->listForHousehold($householdId, true);
        $expenseCategories = array_values(array_filter(
            $allCategories,
            fn (array $c): bool => $c['type'] === 'expense' && $c['archived_at'] === null
        ));

        $totalPlanned = '0.00';
        $totalSpent = '0.00';
        foreach ($expenseCategories as $category) {
            $categoryId = (int) $category['id'];
            if (isset($itemsByCategory[$categoryId])) {
                $totalPlanned = bcadd($totalPlanned, $itemsByCategory[$categoryId]['planned_amount'], 2);
                $totalSpent = bcadd($totalSpent, $actualByCategory[$categoryId] ?? '0.00', 2);
            }
        }

        $budgetedCategoryIds = array_keys($itemsByCategory);
        $unbudgetedSpending = [];
        foreach ($actualByCategory as $categoryId => $amount) {
            if (!in_array($categoryId, $budgetedCategoryIds, true) && bccomp($amount, '0.00', 2) > 0) {
                $unbudgetedSpending[] = [
                    'category_id' => $categoryId,
                    'category_name' => $this->categoryName($allCategories, $categoryId),
                    'amount' => $amount,
                ];
            }
        }

        Response::html(View::render('budgets/index', [
            'periodMonth' => $periodMonth,
            'monthLabel' => date('F Y', strtotime($periodMonth)),
            'prevMonth' => date('Y-m', strtotime($periodMonth . ' -1 month')),
            'nextMonth' => date('Y-m', strtotime($periodMonth . ' +1 month')),
            'expenseCategories' => $expenseCategories,
            'itemsByCategory' => $itemsByCategory,
            'actualByCategory' => $actualByCategory,
            'unbudgetedSpending' => $unbudgetedSpending,
            'totalPlanned' => $totalPlanned,
            'totalSpent' => $totalSpent,
            'totalRemaining' => bcsub($totalPlanned, $totalSpent, 2),
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
        if ($category === null || $category['type'] !== 'expense') {
            $redirectBack('Please choose a valid expense category.');
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

    private function categoryName(array $categories, int $categoryId): string
    {
        foreach ($categories as $category) {
            if ((int) $category['id'] === $categoryId) {
                return $category['name'];
            }
        }

        return 'Unknown category';
    }
}
