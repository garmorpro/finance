<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DashboardWidgets;
use PHPUnit\Framework\TestCase;

/**
 * Rewritten against the widget registry's current shape — the previous
 * version of this file called resolveOrder() with its pre-grouping,
 * one-argument signature and asserted on widget keys ('budget', 'goals',
 * 'recurring', 'net_worth_trend', ...) that no longer exist in
 * App\Support\DashboardWidgets::WIDGETS, both apparently left behind by
 * an earlier refactor that introduced the stats/main grouping without
 * updating this test. composer test had been failing on this file for
 * some time as a result, unrelated to any of that refactor's own
 * correctness.
 */
final class DashboardWidgetsTest extends TestCase
{
    public function test_exists_recognizes_known_and_unknown_keys(): void
    {
        $this->assertTrue(DashboardWidgets::exists('net_worth'));
        $this->assertFalse(DashboardWidgets::exists('not_a_real_widget'));
    }

    public function test_resolve_order_appends_missing_widgets_after_the_saved_order(): void
    {
        $resolved = DashboardWidgets::resolveOrder('main', ['income_by_source']);

        $this->assertSame('income_by_source', $resolved[0]);
        $this->assertContains('capital_allocation', $resolved);
        $this->assertContains('top_expense_categories', $resolved);
    }

    public function test_resolve_order_drops_unknown_saved_keys(): void
    {
        $resolved = DashboardWidgets::resolveOrder('main', ['income_by_source', 'some_widget_that_was_removed']);

        $this->assertNotContains('some_widget_that_was_removed', $resolved);
    }

    public function test_resolve_order_never_loses_or_duplicates_a_known_widget(): void
    {
        $resolved = DashboardWidgets::resolveOrder('stats', ['net_worth']);

        $this->assertSame(count($resolved), count(array_unique($resolved)));
        $this->assertContains('runway', $resolved);
        $this->assertContains('savings_rate', $resolved);
        $this->assertContains('cash_flow', $resolved);
    }

    public function test_resolve_order_never_mixes_groups(): void
    {
        $resolved = DashboardWidgets::resolveOrder('stats', []);

        $this->assertNotContains('capital_allocation', $resolved, 'a "main" widget must never appear in a "stats" order');
    }

    public function test_filter_known_drops_unrecognized_keys(): void
    {
        $filtered = DashboardWidgets::filterKnown(['net_worth', 'made_up_widget', 'cash_flow']);

        $this->assertSame(['net_worth', 'cash_flow'], $filtered);
    }

    public function test_resolve_wide_falls_back_to_built_in_defaults_when_never_saved(): void
    {
        // No widget in the current registry is flagged 'wide' — every
        // main-group tile starts at column width until a user manually
        // resizes it. This is the app's actual current behavior, not an
        // oversight in this test.
        $this->assertSame([], DashboardWidgets::resolveWide(null));
    }

    public function test_resolve_wide_honors_an_explicit_empty_list_as_everything_narrow(): void
    {
        $this->assertSame([], DashboardWidgets::resolveWide([]));
    }

    public function test_resolve_wide_drops_unknown_keys_from_a_saved_list(): void
    {
        $wide = DashboardWidgets::resolveWide(['capital_allocation', 'not_a_widget']);

        $this->assertSame(['capital_allocation'], $wide);
    }
}
