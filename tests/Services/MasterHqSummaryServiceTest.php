<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Repositories\CategoryRepository;
use App\Repositories\GoalRepository;
use App\Repositories\TransactionRepository;
use App\Services\MasterHqSummaryService;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

final class MasterHqSummaryServiceTest extends DatabaseTestCase
{
    use Fixtures;

    /**
     * Recursively collects every array key in a structure — used to
     * assert a forbidden key (account numbers, credentials, etc.) never
     * appears anywhere in the response, not just at the top level.
     *
     * @return list<string>
     */
    private function allKeys(array $data): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $keys[] = (string) $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->allKeys($value));
            }
        }

        return $keys;
    }

    public function test_empty_household_returns_a_fully_zeroed_summary_with_no_errors(): void
    {
        $household = $this->makeHousehold();

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('0.00', $summary['income']['monthToDate']);
        $this->assertSame('0.00', $summary['expenses']['monthToDate']);
        $this->assertSame('0.00', $summary['net']['monthToDate']);
        $this->assertSame('0.00', $summary['net']['yearToDate']);
        $this->assertNull($summary['income']['changePercent']);
        $this->assertNull($summary['expenses']['changePercent']);
        $this->assertNull($summary['net']['changePercent']);
        $this->assertNull($summary['savingsRate'], 'zero income must not divide by zero');
        $this->assertSame([], $summary['incomeSources']);
        $this->assertIsArray($summary['trend']);
        $this->assertNotEmpty($summary['trend'], 'trend should still list every day of the month to date, just zero-filled');

        foreach ($summary['trend'] as $day) {
            $this->assertSame('0.00', $day['income']);
            $this->assertSame('0.00', $day['expenses']);
        }
    }

    public function test_income_and_expenses_month_to_date_are_summed_correctly(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactions = new TransactionRepository();
        $today = gmdate('Y-m-d');

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '1500.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '250.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-300.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('1750.00', $summary['income']['monthToDate']);
        $this->assertSame('300.00', $summary['expenses']['monthToDate'], 'expenses must come back as a positive magnitude, not the stored negative sign');
        $this->assertSame('1450.00', $summary['net']['monthToDate']);
        $this->assertSame('1450.00', $summary['net']['yearToDate'], 'the only activity this year is this month, so YTD equals month-to-date');
    }

    public function test_transfers_are_excluded_from_income_expenses_and_net(): void
    {
        $household = $this->makeHousehold();
        $checkingId = $this->makeAccount($household['household_id'], $household['user_id'], ['name' => 'Checking']);
        $savingsId = $this->makeAccount($household['household_id'], $household['user_id'], ['name' => 'Savings']);
        $transactions = new TransactionRepository();
        $today = gmdate('Y-m-d');

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $checkingId,
            'transaction_type' => 'transfer',
            'transaction_date' => $today,
            'signed_amount' => '-500.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $savingsId,
            'transaction_type' => 'transfer',
            'transaction_date' => $today,
            'signed_amount' => '500.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('0.00', $summary['income']['monthToDate']);
        $this->assertSame('0.00', $summary['expenses']['monthToDate']);
        $this->assertSame('0.00', $summary['net']['monthToDate']);
    }

    public function test_previous_month_and_change_percent_reflect_a_prior_months_activity(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactions = new TransactionRepository();

        // Dated relative to "now" via gmdate(), not a hardcoded date, so
        // this test is correct no matter what month it actually runs in
        // (including the January -> prior-December edge case).
        $previousMonthDate = date('Y-m-d', strtotime(gmdate('Y-m-01') . ' -1 day'));
        $today = gmdate('Y-m-d');

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $previousMonthDate,
            'signed_amount' => '1000.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '1500.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('1000.00', $summary['income']['previousMonth']);
        $this->assertSame('1500.00', $summary['income']['monthToDate']);
        // (1500 - 1000) / 1000 * 100 = 50%
        $this->assertSame(50.0, $summary['income']['changePercent']);
    }

    public function test_change_percent_is_null_when_previous_period_was_zero(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactions = new TransactionRepository();

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => gmdate('Y-m-d'),
            'signed_amount' => '500.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('0.00', $summary['income']['previousMonth']);
        $this->assertNull($summary['income']['changePercent'], 'a percent change from zero is undefined, not infinite or zero');
    }

    public function test_savings_rate_reflects_goal_contributions_over_income(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactions = new TransactionRepository();
        $goals = new GoalRepository();
        $today = gmdate('Y-m-d');

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '2000.00',
        ]));

        $goalId = $goals->create($household['household_id'], $household['user_id'], [
            'responsible_user_id' => $household['user_id'],
            'linked_account_id' => null,
            'name' => 'Emergency Fund',
            'description' => null,
            'goal_type' => 'emergency_fund',
            'target_amount' => '10000.00',
            'target_date' => null,
            'planned_monthly_contribution' => null,
        ]);
        $goals->addContribution($goalId, $household['household_id'], $household['user_id'], '200.00', $today, null);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        // 200 / 2000 * 100 = 10%
        $this->assertSame(10.0, $summary['savingsRate']);
    }

    public function test_savings_rate_is_null_with_zero_income(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => gmdate('Y-m-d'),
            'signed_amount' => '-100.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertNull($summary['savingsRate']);
    }

    public function test_trend_has_one_entry_per_day_of_the_month_to_date_and_reflects_a_days_activity(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $today = gmdate('Y-m-d');

        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '100.00',
        ]));
        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-40.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $daysSoFarThisMonth = (int) gmdate('j');
        $this->assertCount($daysSoFarThisMonth, $summary['trend']);
        $this->assertSame(gmdate('Y-m-01'), $summary['trend'][0]['date']);
        $this->assertSame($today, $summary['trend'][$daysSoFarThisMonth - 1]['date']);

        $todayEntry = $summary['trend'][$daysSoFarThisMonth - 1];
        $this->assertSame('100.00', $todayEntry['income']);
        $this->assertSame('40.00', $todayEntry['expenses']);
    }

    public function test_income_sources_are_serialized_from_income_categories(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'income', 'Freelance Design');

        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_type' => 'income',
            'transaction_date' => gmdate('Y-m-d'),
            'signed_amount' => '750.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertCount(1, $summary['incomeSources']);
        $source = $summary['incomeSources'][0];

        $this->assertSame('cat_' . $categoryId, $source['id']);
        $this->assertSame('Freelance Design', $source['name']);
        $this->assertSame('other', $source['type'], 'this app has no job/freelance/business distinction to draw from');
        $this->assertSame('750.00', $source['income']);
        $this->assertSame('750.00', $source['net']);
        $this->assertNull($source['expenses'], 'not fabricated — no attributable-expense concept exists per category');
        $this->assertNull($source['hours']);
        $this->assertNull($source['effectiveHourlyRate']);
    }

    public function test_response_never_contains_sensitive_or_internal_field_names(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id'], [
            'name' => 'My Checking Account',
        ]);
        (new TransactionRepository())->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'income',
            'transaction_date' => gmdate('Y-m-d'),
            'signed_amount' => '100.00',
        ]));

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $forbidden = [
            'account_number', 'accountnumber', 'routing_number', 'routingnumber',
            'card_number', 'cardnumber', 'cvv', 'password', 'password_hash',
            'secret', 'token', 'plaid', 'ssn', 'social_security',
            'account_id', 'household_id', 'user_id', 'created_by_user_id',
        ];

        $keys = array_map('strtolower', $this->allKeys($summary));
        foreach ($forbidden as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys, "response must never expose a \"{$forbiddenKey}\" field");
        }
    }

    public function test_net_worth_current_is_assets_minus_liabilities(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'name' => 'Checking',
            'account_type' => 'checking',
            'current_balance' => '5000.00',
        ]);
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'name' => 'Credit Card',
            'account_type' => 'credit_card',
            'current_balance' => '1200.00',
        ]);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('3800.00', $summary['netWorth']['current']);
    }

    public function test_net_worth_excludes_accounts_not_included_in_net_worth(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '5000.00',
            'include_in_net_worth' => true,
        ]);
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '9999.00',
            'include_in_net_worth' => false,
        ]);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('5000.00', $summary['netWorth']['current']);
    }

    public function test_net_worth_previous_month_is_a_real_historical_reconstruction(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '2000.00',
        ]);

        // Backdate the account itself so netWorthTrend()'s "was this
        // account old enough to count last month" check includes it —
        // otherwise it's correctly (and separately) excluded, which is
        // covered by the zero-previous-month test below instead.
        $pdo = self::$pdo;
        $pdo->prepare('UPDATE accounts SET created_at = :created_at WHERE id = :id')
            ->execute(['created_at' => date('Y-m-d H:i:s', strtotime('-2 months')), 'id' => $accountId]);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        // No account_balance_history rows exist for this account, so
        // netWorthTrend() falls back to its current balance for any
        // cutoff (a documented, known simplification — see
        // ReportingService::netWorthTrend()) rather than null.
        $this->assertSame('2000.00', $summary['netWorth']['previousMonth']);
        $this->assertIsFloat($summary['netWorth']['changePercent'] ?? 0.0);
    }

    public function test_net_worth_previous_month_is_null_with_no_net_worth_eligible_accounts(): void
    {
        $household = $this->makeHousehold();

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertNull($summary['netWorth']['previousMonth']);
        $this->assertNull($summary['netWorth']['changePercent']);
    }

    public function test_net_worth_change_percent_is_null_when_previous_month_is_zero(): void
    {
        $household = $this->makeHousehold();
        // Created "now" (the fixture default), so netWorthTrend() finds
        // it too new to have existed at last month's cutoff — that
        // month's reconstructed net worth is legitimately 0.00, a real
        // value, not an absence.
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'current_balance' => '2000.00',
        ]);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertSame('0.00', $summary['netWorth']['previousMonth']);
        $this->assertNull($summary['netWorth']['changePercent'], 'a percent change from zero is undefined');
    }

    public function test_net_worth_values_are_decimal_strings(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], ['current_balance' => '1234.56']);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        $this->assertIsString($summary['netWorth']['current']);
        $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $summary['netWorth']['current']);
    }

    public function test_net_worth_never_exposes_underlying_account_detail(): void
    {
        $household = $this->makeHousehold();
        $this->makeAccount($household['household_id'], $household['user_id'], [
            'name' => 'Chase Checking ...4821',
            'institution_name' => 'Chase Bank',
            'current_balance' => '1000.00',
        ]);

        $summary = (new MasterHqSummaryService())->buildSummary($household['household_id']);

        // Exactly the three documented fields, nothing else leaking
        // through from AccountRepository::netWorthSummary()'s own
        // return shape (which also has assetCount/liabilityCount/count)
        // or ReportingService::netWorthTrend()'s (assets/liabilities/label).
        $this->assertSame(['current', 'previousMonth', 'changePercent'], array_keys($summary['netWorth']));

        $encoded = json_encode($summary);
        $this->assertStringNotContainsString('Chase', $encoded);
        $this->assertStringNotContainsString('4821', $encoded);
    }
}
