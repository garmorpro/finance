<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Repositories\TransactionRepository;
use App\Services\InsightsService;
use Tests\DatabaseTestCase;
use Tests\Fixtures;

final class InsightsServiceTest extends DatabaseTestCase
{
    use Fixtures;

    public function test_empty_household_gets_a_graceful_fallback_not_an_empty_card(): void
    {
        $household = $this->makeHousehold();

        $insights = (new InsightsService())->forHousehold($household['household_id']);

        $this->assertNotEmpty($insights, 'a brand-new household must still see something, not a blank card');
        $titles = array_column($insights, 'title');
        $this->assertContains('No spending recorded yet this month', $titles);
        $this->assertContains('Insights get richer with more data', $titles);
    }

    public function test_uncategorized_transactions_produce_a_warning_with_correct_count(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $today = gmdate('Y-m-d');
        $transactions = new TransactionRepository();

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'category_id' => null,
            'signed_amount' => '-40.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'category_id' => null,
            'signed_amount' => '-15.00',
        ]));

        $insights = (new InsightsService())->forHousehold($household['household_id']);

        $this->assertSame('warn', $insights[0]['type']);
        $this->assertSame('2 transactions need a category', $insights[0]['title']);
        $this->assertSame('Review', $insights[0]['link']['label']);
    }

    public function test_a_single_uncategorized_transaction_is_grammatically_singular(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $transactions = new TransactionRepository();

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'transaction_type' => 'expense',
            'transaction_date' => gmdate('Y-m-d'),
            'category_id' => null,
            'signed_amount' => '-40.00',
        ]));

        $insights = (new InsightsService())->forHousehold($household['household_id']);

        $this->assertSame('1 transaction needs a category', $insights[0]['title']);
    }

    public function test_fully_categorized_transactions_get_a_positive_insight(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $categoryId = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $transactions = new TransactionRepository();

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'transaction_type' => 'expense',
            'transaction_date' => gmdate('Y-m-d'),
            'signed_amount' => '-40.00',
        ]));

        $insights = (new InsightsService())->forHousehold($household['household_id']);

        $this->assertSame('positive', $insights[0]['type']);
        $this->assertSame('All transactions are categorized', $insights[0]['title']);
        $this->assertNull($insights[0]['link']);
    }

    public function test_biggest_category_insight_reports_the_top_category_and_its_share(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $groceries = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $gas = $this->makeCategory($household['household_id'], 'expense', 'Transportation');
        $transactions = new TransactionRepository();
        $today = gmdate('Y-m-d');

        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $groceries,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-75.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $gas,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-25.00',
        ]));

        $insights = (new InsightsService())->forHousehold($household['household_id']);
        $biggestCategory = $this->findByTitleContaining($insights, 'biggest category');

        $this->assertNotNull($biggestCategory);
        $this->assertSame('Groceries is your biggest category this month', $biggestCategory['title']);
        $this->assertStringContainsString('75%', $biggestCategory['subtitle']);
    }

    public function test_never_returns_more_than_four_insights(): void
    {
        $household = $this->makeHousehold();
        $accountId = $this->makeAccount($household['household_id'], $household['user_id']);
        $category = $this->makeCategory($household['household_id'], 'expense', 'Groceries');
        $income = $this->makeCategory($household['household_id'], 'income', 'Salary');
        $transactions = new TransactionRepository();
        $today = gmdate('Y-m-d');

        // Enough varied activity that every rule in the service could
        // plausibly fire at once.
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $income,
            'transaction_type' => 'income',
            'transaction_date' => $today,
            'signed_amount' => '3000.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => $category,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-500.00',
        ]));
        $transactions->create($household['household_id'], $household['user_id'], $this->transactionData([
            'account_id' => $accountId,
            'category_id' => null,
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'signed_amount' => '-20.00',
        ]));

        $insights = (new InsightsService())->forHousehold($household['household_id']);

        $this->assertLessThanOrEqual(4, count($insights));
    }

    /**
     * @param list<array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}> $insights
     * @return array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}|null
     */
    private function findByTitleContaining(array $insights, string $needle): ?array
    {
        foreach ($insights as $insight) {
            if (str_contains($insight['title'], $needle)) {
                return $insight;
            }
        }

        return null;
    }
}
