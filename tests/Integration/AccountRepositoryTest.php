<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AccountRepository;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

final class AccountRepositoryTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_create_and_find_a_checking_account(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id'], [
            'name' => 'Primary Checking',
            'account_type' => 'checking',
            'current_balance' => '2500.00',
        ]);

        $account = (new AccountRepository())->findById($accountId, $household['household_id']);

        $this->assertNotNull($account);
        $this->assertSame('Primary Checking', $account['name']);
        $this->assertSame('2500.00', $account['current_balance']);
        $this->assertSame('active', $account['status']);
    }

    public function test_update_balance_never_overwrites_history_it_just_moves_current_balance(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id'], ['current_balance' => '100.00']);

        (new AccountRepository())->updateBalance($accountId, $household['household_id'], '175.50');

        $account = (new AccountRepository())->findById($accountId, $household['household_id']);
        $this->assertSame('175.50', $account['current_balance']);
    }

    public function test_archived_accounts_are_excluded_by_default_but_included_on_request(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);

        (new AccountRepository())->setStatus($accountId, $household['household_id'], 'archived');

        $activeOnly = (new AccountRepository())->listForHousehold($household['household_id']);
        $includingArchived = (new AccountRepository())->listForHousehold($household['household_id'], true);

        $this->assertCount(0, $activeOnly);
        $this->assertCount(1, $includingArchived);
        $this->assertSame('archived', $includingArchived[0]['status']);
    }

    public function test_is_liability_classifies_account_types_correctly(): void
    {
        $this->assertTrue(AccountRepository::isLiability('credit_card'));
        $this->assertTrue(AccountRepository::isLiability('mortgage'));
        $this->assertFalse(AccountRepository::isLiability('checking'));
        $this->assertFalse(AccountRepository::isLiability('investment'));
    }

    public function test_net_worth_summary_separates_assets_from_liabilities(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'account_type' => 'checking',
            'current_balance' => '5000.00',
        ]);
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'account_type' => 'credit_card',
            'current_balance' => '1200.00',
        ]);

        $summary = (new AccountRepository())->netWorthSummary($household['household_id']);

        $this->assertSame('5000.00', $summary['assets']);
        $this->assertSame('1200.00', $summary['liabilities']);
        $this->assertSame('3800.00', $summary['net']);
        $this->assertSame(2, $summary['count']);
    }

    public function test_net_worth_summary_excludes_accounts_not_flagged_for_it(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '1000.00',
            'include_in_net_worth' => true,
        ]);
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '9999.00',
            'include_in_net_worth' => false,
        ]);

        $summary = (new AccountRepository())->netWorthSummary($household['household_id']);

        $this->assertSame('1000.00', $summary['assets']);
    }
}
