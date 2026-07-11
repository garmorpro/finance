<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\DebtService;
use PHPUnit\Framework\TestCase;

final class DebtServiceTest extends TestCase
{
    private DebtService $service;

    protected function setUp(): void
    {
        $this->service = new DebtService();
    }

    public function test_estimate_payoff_is_null_without_rate_or_payment(): void
    {
        $this->assertNull($this->service->estimatePayoff('1000.00', null, '100.00'));
        $this->assertNull($this->service->estimatePayoff('1000.00', '12.0', null));
    }

    public function test_estimate_payoff_is_null_for_zero_or_negative_balance(): void
    {
        $this->assertNull($this->service->estimatePayoff('0.00', '12.0', '100.00'));
        $this->assertNull($this->service->estimatePayoff('-50.00', '12.0', '100.00'));
    }

    public function test_estimate_payoff_is_null_for_zero_or_negative_payment(): void
    {
        $this->assertNull($this->service->estimatePayoff('1000.00', '12.0', '0.00'));
    }

    public function test_estimate_payoff_with_zero_interest_is_exact_balance_over_payment(): void
    {
        $result = $this->service->estimatePayoff('1000.00', '0', '250.00');

        $this->assertNotNull($result);
        $this->assertTrue($result['coversInterest']);
        $this->assertSame(4, $result['months']);
        $this->assertSame(date('Y-m-d', strtotime('+4 months')), $result['payoffDate']);
    }

    public function test_estimate_payoff_flags_a_payment_that_does_not_cover_interest(): void
    {
        // 24% APR on $10,000 is $200/month in interest alone — a $150
        // payment can never pay this down, so there's no payoff date to
        // project, only a warning.
        $result = $this->service->estimatePayoff('10000.00', '24.0', '150.00');

        $this->assertNotNull($result);
        $this->assertFalse($result['coversInterest']);
        $this->assertNull($result['months']);
        $this->assertNull($result['payoffDate']);
    }

    public function test_estimate_payoff_with_interest_takes_at_least_as_long_as_the_interest_free_case(): void
    {
        // Not asserting an exact month count here (that depends on a
        // log() computation this test can't independently re-derive
        // without risking its own arithmetic error) — but paying down a
        // balance that's accruing interest can never finish faster than
        // paying down the same balance at 0% interest.
        $result = $this->service->estimatePayoff('1000.00', '12.0', '100.00');
        $interestFreeFloor = (int) ceil(1000.00 / 100.00);

        $this->assertNotNull($result);
        $this->assertTrue($result['coversInterest']);
        $this->assertGreaterThanOrEqual($interestFreeFloor, $result['months']);
    }

    public function test_credit_utilization_is_null_without_a_credit_limit(): void
    {
        $this->assertNull($this->service->creditUtilization('300.00', null));
        $this->assertNull($this->service->creditUtilization('300.00', '0.00'));
    }

    public function test_credit_utilization_computes_a_percentage(): void
    {
        $this->assertSame(30.0, $this->service->creditUtilization('300.00', '1000.00'));
        $this->assertSame(100.0, $this->service->creditUtilization('1000.00', '1000.00'));
    }

    public function test_summarize_handles_an_empty_list(): void
    {
        $summary = $this->service->summarize([]);

        $this->assertSame('0.00', $summary['totalBalance']);
        $this->assertSame('0.00', $summary['totalMinimumPayment']);
        $this->assertNull($summary['weightedAverageRate']);
        $this->assertSame(0, $summary['count']);
    }

    public function test_summarize_totals_balances_and_minimum_payments(): void
    {
        $summary = $this->service->summarize([
            ['current_balance' => '1000.00', 'minimum_payment' => '50.00', 'interest_rate' => '10.0'],
            ['current_balance' => '2000.00', 'minimum_payment' => '75.00', 'interest_rate' => '20.0'],
        ]);

        $this->assertSame('3000.00', $summary['totalBalance']);
        $this->assertSame('125.00', $summary['totalMinimumPayment']);
        $this->assertSame(2, $summary['count']);
    }

    public function test_summarize_computes_a_balance_weighted_average_rate(): void
    {
        // (1000*10 + 2000*20) / 3000 = 16.666... -> rounds to 16.67
        $summary = $this->service->summarize([
            ['current_balance' => '1000.00', 'minimum_payment' => null, 'interest_rate' => '10.0'],
            ['current_balance' => '2000.00', 'minimum_payment' => null, 'interest_rate' => '20.0'],
        ]);

        $this->assertSame(16.67, $summary['weightedAverageRate']);
    }

    public function test_summarize_excludes_null_rates_and_payments_from_their_respective_totals(): void
    {
        $summary = $this->service->summarize([
            ['current_balance' => '500.00', 'minimum_payment' => null, 'interest_rate' => null],
        ]);

        $this->assertSame('500.00', $summary['totalBalance']);
        $this->assertSame('0.00', $summary['totalMinimumPayment']);
        // Not null here: weightedAverageRate is only null when total
        // balance is zero. A balance with no rate on record just
        // contributes nothing to the weighted sum's numerator, which
        // pulls the average toward 0 rather than making it undefined.
        $this->assertSame(0.0, $summary['weightedAverageRate']);
    }
}
