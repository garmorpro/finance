<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DashboardWidgets;
use PHPUnit\Framework\TestCase;

/**
 * Rewritten against the widget registry's current shape — Net Worth,
 * Runway, Savings Rate, and Cash Flow (the old 'stats' group) and
 * Capital Allocation were removed from App\Support\DashboardWidgets::
 * WIDGETS when the Overview redesign merged them into the fixed hero
 * band and waterfall (see dashboard/widgets/hero_stats.php and
 * dashboard/widgets/waterfall.php). Only the 'main' group remains.
 */
final class DashboardWidgetsTest extends TestCase
{
    public function test_exists_recognizes_known_and_unknown_keys(): void
    {
        $this->assertTrue(DashboardWidgets::exists('income_by_source'));
        $this->assertFalse(DashboardWidgets::exists('net_worth'));
        $this->assertFalse(DashboardWidgets::exists('not_a_real_widget'));
    }

    public function test_resolve_order_appends_missing_widgets_after_the_saved_order(): void
    {
        $resolved = DashboardWidgets::resolveOrder('main', ['income_by_source']);

        $this->assertSame('income_by_source', $resolved[0]);
        $this->assertContains('top_expense_categories', $resolved);
    }

    public function test_resolve_order_drops_unknown_saved_keys(): void
    {
        $resolved = DashboardWidgets::resolveOrder('main', ['income_by_source', 'some_widget_that_was_removed']);

        $this->assertNotContains('some_widget_that_was_removed', $resolved);
    }

    public function test_resolve_order_never_loses_or_duplicates_a_known_widget(): void
    {
        $resolved = DashboardWidgets::resolveOrder('main', ['top_expense_categories']);

        $this->assertSame(count($resolved), count(array_unique($resolved)));
        $this->assertContains('income_by_source', $resolved);
        $this->assertContains('top_expense_categories', $resolved);
    }

    public function test_resolve_order_ignores_the_retired_stats_group(): void
    {
        // 'stats' no longer has any widgets in it at all — a saved
        // order for it should resolve to an empty list, not silently
        // fall back to the main group's widgets.
        $resolved = DashboardWidgets::resolveOrder('stats', ['net_worth']);

        $this->assertSame([], $resolved);
    }

    public function test_filter_known_drops_unrecognized_keys(): void
    {
        $filtered = DashboardWidgets::filterKnown(['income_by_source', 'made_up_widget', 'top_expense_categories']);

        $this->assertSame(['income_by_source', 'top_expense_categories'], $filtered);
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
        $wide = DashboardWidgets::resolveWide(['income_by_source', 'not_a_widget']);

        $this->assertSame(['income_by_source'], $wide);
    }
}
