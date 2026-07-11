<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\RecurringItemRepository;
use PHPUnit\Framework\TestCase;

/**
 * Covers RecurringItemRepository's two static helpers, which are pure
 * functions (no DB) despite living on a repository class.
 */
final class RecurringItemRepositoryStaticTest extends TestCase
{
    public function test_monthly_equivalent_for_weekly_frequency(): void
    {
        $this->assertSame('520.00', RecurringItemRepository::monthlyEquivalent('weekly', '120.00'));
    }

    public function test_monthly_equivalent_for_biweekly_frequency(): void
    {
        $this->assertSame('260.00', RecurringItemRepository::monthlyEquivalent('biweekly', '120.00'));
    }

    public function test_monthly_equivalent_for_quarterly_frequency(): void
    {
        $this->assertSame('100.00', RecurringItemRepository::monthlyEquivalent('quarterly', '300.00'));
    }

    public function test_monthly_equivalent_for_annual_frequency(): void
    {
        $this->assertSame('100.00', RecurringItemRepository::monthlyEquivalent('annually', '1200.00'));
    }

    public function test_monthly_equivalent_for_monthly_frequency_is_unchanged(): void
    {
        $this->assertSame('150.00', RecurringItemRepository::monthlyEquivalent('monthly', '150.00'));
    }

    public function test_advance_due_date_weekly(): void
    {
        $this->assertSame('2026-01-08', RecurringItemRepository::advanceDueDate('2026-01-01', 'weekly'));
    }

    public function test_advance_due_date_biweekly(): void
    {
        $this->assertSame('2026-01-15', RecurringItemRepository::advanceDueDate('2026-01-01', 'biweekly'));
    }

    public function test_advance_due_date_monthly(): void
    {
        $this->assertSame('2026-02-01', RecurringItemRepository::advanceDueDate('2026-01-01', 'monthly'));
    }

    public function test_advance_due_date_quarterly(): void
    {
        $this->assertSame('2026-04-01', RecurringItemRepository::advanceDueDate('2026-01-01', 'quarterly'));
    }

    public function test_advance_due_date_annually(): void
    {
        $this->assertSame('2027-01-01', RecurringItemRepository::advanceDueDate('2026-01-01', 'annually'));
    }

    public function test_advance_due_date_anchors_to_the_date_that_was_due_not_today(): void
    {
        // A bill due months ago, marked paid today, should advance from
        // its own due date — not jump to "one month from today" — or a
        // household that's behind on marking bills paid would see its
        // cadence drift forward every time they catch up late.
        $this->assertSame('2025-02-01', RecurringItemRepository::advanceDueDate('2025-01-01', 'monthly'));
    }
}
