<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

final class BudgetRepositoryTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_actual_by_category_sums_expenses_as_a_positive_amount(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $transactionRepo = new TransactionRepository();
        $month = gmdate('Y-m');
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_date' => "{$month}-05",
            'signed_amount' => '-60.00',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_date' => "{$month}-15",
            'signed_amount' => '-40.00',
        ]));

        $monthStart = gmdate('Y-m-01');
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
        $actual = (new BudgetRepository())->actualByCategory($household['household_id'], 'expense', $monthStart, $monthEnd);

        $this->assertSame('100.00', $actual[$categoryId]);
    }

    public function test_actual_by_category_excludes_transactions_marked_exclude_from_budget(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_date' => gmdate('Y-m') . '-15',
            'signed_amount' => '-60.00',
            'exclude_from_budget' => true,
        ]));

        $monthStart = gmdate('Y-m-01');
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
        $actual = (new BudgetRepository())->actualByCategory($household['household_id'], 'expense', $monthStart, $monthEnd);

        $this->assertArrayNotHasKey($categoryId, $actual);
    }

    public function test_actual_by_category_only_includes_the_requested_transaction_type(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $expenseCategoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $incomeCategoryId = $this->makeCategory($household['household_id'], 'income', 'Salary');

        $transactionRepo = new TransactionRepository();
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $expenseCategoryId,
            'transaction_date' => gmdate('Y-m') . '-15',
            'signed_amount' => '-60.00',
        ]));
        $transactionRepo->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $incomeCategoryId,
            'transaction_type' => 'income',
            'transaction_date' => gmdate('Y-m') . '-15',
            'signed_amount' => '3000.00',
        ]));

        $monthStart = gmdate('Y-m-01');
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
        $expenseActual = (new BudgetRepository())->actualByCategory($household['household_id'], 'expense', $monthStart, $monthEnd);

        $this->assertArrayHasKey($expenseCategoryId, $expenseActual);
        $this->assertArrayNotHasKey($incomeCategoryId, $expenseActual);
    }

    public function test_month_summary_reports_planned_spent_and_remaining(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $budgetRepo = new BudgetRepository();
        $month = gmdate('Y-m-01');
        $budget = $budgetRepo->findOrCreateForMonth($household['household_id'], $month);
        $budgetRepo->upsertItem((int) $budget['id'], $categoryId, '500.00');

        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_date' => gmdate('Y-m') . '-15',
            'signed_amount' => '-300.00',
        ]));

        $summary = $budgetRepo->monthSummary($household['household_id'], $month);

        $this->assertTrue($summary['has_items']);
        $this->assertSame('500.00', $summary['planned']);
        $this->assertSame('300.00', $summary['spent']);
        $this->assertSame('200.00', $summary['remaining']);
    }

    public function test_month_summary_reports_no_items_for_an_empty_month(): void
    {
        $household = $this->makeHousehold();

        $summary = (new BudgetRepository())->monthSummary($household['household_id'], gmdate('Y-m-01'));

        $this->assertFalse($summary['has_items']);
        $this->assertSame('0.00', $summary['planned']);
    }

    public function test_month_summary_ignores_income_budget_items_in_its_expense_only_totals(): void
    {
        $household = $this->makeHousehold();
        $expenseCategoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $incomeCategoryId = $this->makeCategory($household['household_id'], 'income', 'Salary');

        $budgetRepo = new BudgetRepository();
        $month = gmdate('Y-m-01');
        $budget = $budgetRepo->findOrCreateForMonth($household['household_id'], $month);
        $budgetRepo->upsertItem((int) $budget['id'], $expenseCategoryId, '500.00');
        $budgetRepo->upsertItem((int) $budget['id'], $incomeCategoryId, '3000.00');

        $summary = $budgetRepo->monthSummary($household['household_id'], $month);

        $this->assertSame('500.00', $summary['planned']);
    }

    public function test_copy_items_from_skips_categories_already_set_on_the_target(): void
    {
        $household = $this->makeHousehold();
        $categoryA = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $categoryB = $this->makeCategory($household['household_id'], 'expense', 'Rent');

        $budgetRepo = new BudgetRepository();
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $thisMonth = gmdate('Y-m-01');

        $sourceBudget = $budgetRepo->findOrCreateForMonth($household['household_id'], $lastMonth);
        $budgetRepo->upsertItem((int) $sourceBudget['id'], $categoryA, '400.00');
        $budgetRepo->upsertItem((int) $sourceBudget['id'], $categoryB, '1200.00');

        $targetBudget = $budgetRepo->findOrCreateForMonth($household['household_id'], $thisMonth);
        $budgetRepo->upsertItem((int) $targetBudget['id'], $categoryB, '1500.00');

        $copied = $budgetRepo->copyItemsFrom((int) $sourceBudget['id'], (int) $targetBudget['id']);

        $this->assertSame(1, $copied);

        $items = $budgetRepo->listItemsForBudget((int) $targetBudget['id']);
        $byCategory = [];
        foreach ($items as $item) {
            $byCategory[(int) $item['category_id']] = $item['planned_amount'];
        }

        $this->assertSame('400.00', $byCategory[$categoryA]);
        $this->assertSame('1500.00', $byCategory[$categoryB], 'Existing target amount must not be overwritten by the copy.');
    }

    public function test_find_or_create_for_month_does_not_write_a_row_for_categories_with_a_default(): void
    {
        // Defaults are layered on top for display only (see
        // BudgetController::index()) — findOrCreateForMonth() itself must
        // stay side-effect-free regarding budget_items, or "apply to all
        // future months" would only ever reach months nobody had already
        // (even passively) visited before the default was set.
        $household = $this->makeHousehold();
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $budgetRepo = new BudgetRepository();
        $budgetRepo->upsertDefault($household['household_id'], $categoryId, '450.00', gmdate('Y-m-01'));

        $budget = $budgetRepo->findOrCreateForMonth($household['household_id'], gmdate('Y-m-01'));

        $this->assertSame([], $budgetRepo->listItemsForBudget((int) $budget['id']));
    }

    public function test_defaults_for_household_excludes_defaults_not_yet_effective(): void
    {
        $household = $this->makeHousehold();
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $budgetRepo = new BudgetRepository();
        $budgetRepo->upsertDefault($household['household_id'], $categoryId, '450.00', '2026-03-01');

        $before = $budgetRepo->defaultsForHousehold($household['household_id'], '2026-02-01');
        $onMonth = $budgetRepo->defaultsForHousehold($household['household_id'], '2026-03-01');
        $after = $budgetRepo->defaultsForHousehold($household['household_id'], '2026-04-01');

        $this->assertArrayNotHasKey($categoryId, $before, 'A default must not apply to a month before it took effect.');
        $this->assertSame('450.00', $onMonth[$categoryId]);
        $this->assertSame('450.00', $after[$categoryId]);
    }

    public function test_upsert_default_replaces_the_previous_default_for_the_same_category(): void
    {
        $household = $this->makeHousehold();
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $budgetRepo = new BudgetRepository();
        $budgetRepo->upsertDefault($household['household_id'], $categoryId, '450.00', '2026-01-01');
        $budgetRepo->upsertDefault($household['household_id'], $categoryId, '500.00', '2026-03-01');

        $defaults = $budgetRepo->defaultsForHousehold($household['household_id'], '2026-03-01');

        $this->assertSame('500.00', $defaults[$categoryId]);
    }

    public function test_actuals_by_category_trailing_months_zero_fills_months_with_no_activity(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');

        $currentMonth = gmdate('Y-m-01');
        $twoMonthsAgo = date('Y-m-d', strtotime($currentMonth . ' -2 months'));
        $twoMonthsAgoKey = date('Y-m', strtotime($twoMonthsAgo));

        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_date' => date('Y-m-15', strtotime($twoMonthsAgo)),
            'signed_amount' => '-75.00',
        ]));

        $byMonth = (new BudgetRepository())->actualsByCategoryTrailingMonths(
            $household['household_id'],
            $categoryId,
            'expense',
            $currentMonth,
            3
        );

        $this->assertCount(3, $byMonth);
        $this->assertSame('75.00', $byMonth[$twoMonthsAgoKey]);

        foreach ($byMonth as $ym => $amount) {
            if ($ym !== $twoMonthsAgoKey) {
                $this->assertSame('0.00', $amount);
            }
        }
    }
}
