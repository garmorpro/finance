<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AccountRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\GoalRepository;
use App\Repositories\RecurringItemRepository;
use App\Repositories\TransactionRepository;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

/**
 * CLAUDE.md: "Every record-level request must verify that the
 * authenticated user is authorized to access the requested household and
 * record." This suite checks that directly: a record created under one
 * household must be invisible through every repository when queried with
 * a *different* household's ID, even when the record ID is guessed
 * correctly (the IDOR case — sequential auto-increment IDs make this
 * trivial to attempt).
 */
final class HouseholdIsolationTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_account_is_invisible_to_a_different_household(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');

        $accountId = $this->makeAccount($householdA['household_id'], $householdA['user_id']);

        $this->assertNotNull((new AccountRepository())->findById($accountId, $householdA['household_id']));
        $this->assertNull((new AccountRepository())->findById($accountId, $householdB['household_id']));
    }

    public function test_account_list_never_includes_another_households_accounts(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');

        $this->makeAccount($householdA['household_id'], $householdA['user_id'], ['name' => 'A Checking']);
        $this->makeAccount($householdB['household_id'], $householdB['user_id'], ['name' => 'B Checking']);

        $accountsForA = (new AccountRepository())->listForHousehold($householdA['household_id']);

        $this->assertCount(1, $accountsForA);
        $this->assertSame('A Checking', $accountsForA[0]['name']);
    }

    public function test_transaction_is_invisible_to_a_different_household(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');
        $accountId = $this->makeAccount($householdA['household_id'], $householdA['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($householdA['household_id'], $householdA['user_id'], $this->transactionData([
            'account_id' => $accountId,
        ]));

        $this->assertNotNull($transactionRepo->findById($transactionId, $householdA['household_id']));
        $this->assertNull($transactionRepo->findById($transactionId, $householdB['household_id']));
    }

    public function test_updating_a_transaction_under_the_wrong_household_id_silently_touches_nothing(): void
    {
        // update()'s WHERE clause includes household_id, so a wrong ID
        // isn't an error — it's a no-op, which is exactly what should
        // happen: household B can't accidentally (or deliberately)
        // rewrite household A's data by guessing a transaction ID.
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');
        $accountId = $this->makeAccount($householdA['household_id'], $householdA['user_id']);

        $transactionRepo = new TransactionRepository();
        $transactionId = $transactionRepo->create($householdA['household_id'], $householdA['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'payee' => 'Original Payee',
        ]));

        $transactionRepo->update($transactionId, $householdB['household_id'], $householdB['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'payee' => 'Hijacked Payee',
        ]));

        $untouched = $transactionRepo->findById($transactionId, $householdA['household_id']);
        $this->assertSame('Original Payee', $untouched['payee']);
    }

    public function test_budget_data_is_scoped_per_household(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');

        $budgetRepo = new BudgetRepository();
        $month = gmdate('Y-m-01');
        $budgetA = $budgetRepo->findOrCreateForMonth($householdA['household_id'], $month);

        $this->assertNull($budgetRepo->findForMonth($householdB['household_id'], $month));
        $this->assertNotNull($budgetRepo->findForMonth($householdA['household_id'], $month));
        $this->assertNotSame($budgetA['id'], $budgetRepo->findOrCreateForMonth($householdB['household_id'], $month)['id']);
    }

    public function test_goal_is_invisible_to_a_different_household(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');

        $goalId = (new GoalRepository())->create($householdA['household_id'], $householdA['user_id'], [
            'responsible_user_id' => null,
            'linked_account_id' => null,
            'name' => 'Emergency Fund',
            'description' => null,
            'goal_type' => 'emergency_fund',
            'target_amount' => '1000.00',
            'target_date' => null,
            'planned_monthly_contribution' => null,
        ]);

        $this->assertNotNull((new GoalRepository())->findById($goalId, $householdA['household_id']));
        $this->assertNull((new GoalRepository())->findById($goalId, $householdB['household_id']));
    }

    public function test_recurring_item_is_invisible_to_a_different_household(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');
        $accountId = $this->makeAccount($householdA['household_id'], $householdA['user_id']);

        $recurringId = (new RecurringItemRepository())->create($householdA['household_id'], $householdA['user_id'], [
            'account_id' => $accountId,
            'category_id' => null,
            'name' => 'Netflix',
            'recurring_type' => 'expense',
            'expected_amount' => '15.99',
            'frequency' => 'monthly',
            'next_due_date' => gmdate('Y-m-d'),
            'auto_pay' => false,
            'notes' => null,
        ]);

        $this->assertNotNull((new RecurringItemRepository())->findById($recurringId, $householdA['household_id']));
        $this->assertNull((new RecurringItemRepository())->findById($recurringId, $householdB['household_id']));
    }

    /**
     * Public registration (RegistrationController::register(), and
     * bin/create-owner.php before it) creates a household through the
     * exact same sequence Fixtures::makeHousehold() uses above — create
     * user, create household, add as owner — plus one step that sequence
     * doesn't: seeding default categories. Every other test in this file
     * already proves the shared part of that sequence is isolated; this
     * one specifically locks in the one piece registration adds, so a
     * future change to how registration seeds a new household can't
     * silently leak categories across households. The controller itself
     * isn't exercised directly here — see docs/testing.md's "not covered
     * by automated tests" list for why (session/header-dependent flows
     * don't unit-test cleanly without a larger HTTP-test harness).
     */
    public function test_registration_seeded_categories_are_isolated_between_households(): void
    {
        $householdA = $this->makeHousehold('Household A');
        $householdB = $this->makeHousehold('Household B');

        (new CategoryRepository())->seedDefaults($householdA['household_id']);

        $categoriesForA = (new CategoryRepository())->listForHousehold($householdA['household_id']);
        $categoriesForB = (new CategoryRepository())->listForHousehold($householdB['household_id']);

        $this->assertNotEmpty($categoriesForA);
        $this->assertSame([], $categoriesForB);
    }
}
