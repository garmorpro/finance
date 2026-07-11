<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DashboardWidgets;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetsTest extends TestCase
{
    public function test_exists_recognizes_known_and_unknown_keys(): void
    {
        $this->assertTrue(DashboardWidgets::exists('net_worth'));
        $this->assertFalse(DashboardWidgets::exists('not_a_real_widget'));
    }

    public function test_resolve_order_appends_missing_widgets_after_the_saved_order(): void
    {
        $resolved = DashboardWidgets::resolveOrder(['recurring', 'net_worth']);

        $this->assertSame('recurring', $resolved[0]);
        $this->assertSame('net_worth', $resolved[1]);
        $this->assertContains('budget', $resolved);
        $this->assertContains('goals', $resolved);
    }

    public function test_resolve_order_drops_unknown_saved_keys(): void
    {
        $resolved = DashboardWidgets::resolveOrder(['net_worth', 'some_widget_that_was_removed']);

        $this->assertNotContains('some_widget_that_was_removed', $resolved);
    }

    public function test_resolve_order_never_loses_or_duplicates_a_known_widget(): void
    {
        $resolved = DashboardWidgets::resolveOrder(['budget']);

        $this->assertSame(count($resolved), count(array_unique($resolved)));
        $this->assertContains('net_worth', $resolved);
    }

    public function test_filter_known_drops_unrecognized_keys(): void
    {
        $filtered = DashboardWidgets::filterKnown(['budget', 'made_up_widget', 'goals']);

        $this->assertSame(['budget', 'goals'], $filtered);
    }

    public function test_resolve_wide_falls_back_to_built_in_defaults_when_never_saved(): void
    {
        $wide = DashboardWidgets::resolveWide(null);

        $this->assertContains('net_worth_trend', $wide);
        $this->assertContains('spending', $wide);
        $this->assertContains('income_vs_spending', $wide);
        $this->assertNotContains('net_worth', $wide);
    }

    public function test_resolve_wide_honors_an_explicit_empty_list_as_everything_narrow(): void
    {
        $this->assertSame([], DashboardWidgets::resolveWide([]));
    }

    public function test_resolve_wide_drops_unknown_keys_from_a_saved_list(): void
    {
        $wide = DashboardWidgets::resolveWide(['budget', 'not_a_widget']);

        $this->assertSame(['budget'], $wide);
    }
}
