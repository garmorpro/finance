<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\GoalService;
use PHPUnit\Framework\TestCase;

final class GoalServiceTest extends TestCase
{
    private GoalService $service;

    protected function setUp(): void
    {
        $this->service = new GoalService();
    }

    public function test_progress_percent_computes_a_simple_ratio(): void
    {
        $this->assertSame(25.0, $this->service->progressPercent('50.00', '200.00'));
    }

    public function test_progress_percent_is_uncapped_for_an_overfunded_goal(): void
    {
        $this->assertSame(125.0, $this->service->progressPercent('250.00', '200.00'));
    }

    public function test_progress_percent_is_zero_when_target_is_zero_or_negative(): void
    {
        $this->assertSame(0.0, $this->service->progressPercent('50.00', '0.00'));
    }

    public function test_estimated_completion_is_null_without_a_planned_contribution(): void
    {
        $this->assertNull($this->service->estimatedCompletion('0.00', '1200.00', null));
        $this->assertNull($this->service->estimatedCompletion('0.00', '1200.00', '0.00'));
    }

    public function test_estimated_completion_is_null_when_already_funded(): void
    {
        $this->assertNull($this->service->estimatedCompletion('1200.00', '1200.00', '100.00'));
        $this->assertNull($this->service->estimatedCompletion('1500.00', '1200.00', '100.00'));
    }

    public function test_estimated_completion_projects_months_and_a_date(): void
    {
        $result = $this->service->estimatedCompletion('0.00', '1200.00', '100.00');

        $this->assertNotNull($result);
        $this->assertSame(12, $result['months']);
        $this->assertSame(date('Y-m-d', strtotime('+12 months')), $result['date']);
    }

    public function test_estimated_completion_rounds_partial_months_up(): void
    {
        // 250 remaining at 100/month takes 2.5 months — never claim it's
        // done in less time than it actually is.
        $result = $this->service->estimatedCompletion('0.00', '250.00', '100.00');

        $this->assertSame(3, $result['months']);
    }

    public function test_suggested_monthly_contribution_is_null_without_a_target_date(): void
    {
        $this->assertNull($this->service->suggestedMonthlyContribution('0.00', '1200.00', null));
    }

    public function test_suggested_monthly_contribution_is_null_when_already_funded(): void
    {
        $this->assertNull($this->service->suggestedMonthlyContribution('1200.00', '1200.00', date('Y-m-d', strtotime('+6 months'))));
    }

    public function test_suggested_monthly_contribution_is_null_for_a_past_target_date(): void
    {
        $this->assertNull($this->service->suggestedMonthlyContribution('0.00', '1200.00', date('Y-m-d', strtotime('-1 month'))));
    }

    public function test_suggested_monthly_contribution_divides_remaining_amount_by_months_remaining(): void
    {
        // Anchored to the 1st of the target month rather than "today + 6
        // months" directly — if today is the 29th/30th/31st and the
        // target month is shorter, strtotime's day-of-month rollover can
        // push the raw date a day or two past the true 6-month mark and
        // flip this to 7 months. The 1st always exists, so this is exact
        // and stable regardless of what day the suite happens to run on.
        $targetDate = date('Y-m-01', strtotime('+6 months'));

        $this->assertSame('200.00', $this->service->suggestedMonthlyContribution('0.00', '1200.00', $targetDate));
    }
}
