<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registry of dashboard tiles. `available` widgets render real data;
 * unavailable ones render an honest "coming soon" placeholder rather
 * than fabricated numbers, until their feature phase is built.
 */
final class DashboardWidgets
{
    /**
     * "The Lifecycle of a Dollar" is deliberately not in this registry —
     * it's rendered as a fixed sticky sidebar panel next to this grid
     * (see dashboard/index.php), not a draggable/hideable tile.
     */
    private const WIDGETS = [
        'runway' => ['title' => 'Runway', 'available' => true],
        'savings_rate' => ['title' => 'Savings Rate', 'available' => true],
        'net_worth' => ['title' => 'Net Worth', 'available' => true],
        'cash_flow' => ['title' => 'Cash Flow This Month', 'available' => true],
        'capital_allocation' => ['title' => 'Capital Allocation', 'available' => true],
        'income_by_source' => ['title' => 'Income by Source', 'available' => true],
        'top_expense_categories' => ['title' => 'Top Expense Categories', 'available' => true],
    ];

    /** @var list<string> */
    private const DEFAULT_ORDER = [
        'runway', 'savings_rate', 'net_worth', 'cash_flow',
        'capital_allocation', 'income_by_source', 'top_expense_categories',
    ];

    /**
     * A fingerprint of the current widget set. Saved layouts are only
     * honored when they were saved against this exact set — see
     * UserRepository::getDashboardLayout(). Widgets have been added,
     * removed, and renamed several times during this redesign; without
     * this, a saved layout from an earlier widget set would keep
     * partially applying (known keys kept, new ones just appended to the
     * end) instead of resetting cleanly to the new defaults.
     */
    public static function fingerprint(): string
    {
        return implode(',', array_keys(self::WIDGETS));
    }

    public static function title(string $key): string
    {
        return self::WIDGETS[$key]['title'] ?? ucfirst($key);
    }

    public static function isAvailable(string $key): bool
    {
        return self::WIDGETS[$key]['available'] ?? false;
    }

    public static function isWide(string $key): bool
    {
        return self::WIDGETS[$key]['wide'] ?? false;
    }

    public static function exists(string $key): bool
    {
        return isset(self::WIDGETS[$key]);
    }

    /**
     * Merges a user's saved order with the canonical widget list, so newly
     * added widgets always appear even if the user's saved layout predates
     * them, and any stale/unknown keys in a saved layout are dropped. If a
     * saved layout matches none of the current widgets at all (it predates
     * a full widget-set replacement, like this redesign), it's discarded
     * entirely rather than kept as a near-empty list with everything else
     * just appended after it.
     *
     * @param list<string> $savedOrder
     * @return list<string>
     */
    public static function resolveOrder(array $savedOrder): array
    {
        if (self::isStale($savedOrder)) {
            return self::DEFAULT_ORDER;
        }

        $known = array_values(array_filter($savedOrder, fn (string $key): bool => self::exists($key)));
        $missing = array_values(array_diff(self::DEFAULT_ORDER, $known));

        return [...$known, ...$missing];
    }

    /**
     * True when a saved list references at least one widget and none of
     * them exist anymore — i.e. it's leftover from before a full
     * widget-set replacement, not a real (if partial) customization.
     *
     * @param list<string> $savedKeys
     */
    private static function isStale(array $savedKeys): bool
    {
        return $savedKeys !== [] && array_intersect($savedKeys, array_keys(self::WIDGETS)) === [];
    }

    /**
     * @param list<string> $hidden
     * @return list<string>
     */
    public static function filterKnown(array $hidden): array
    {
        return array_values(array_filter($hidden, fn (string $key): bool => self::exists($key)));
    }

    /**
     * Resolves the effective set of full-width tiles: the user's saved
     * choice if they've ever touched a resize toggle (even to an empty
     * set), otherwise each widget's own built-in default. A saved choice
     * that matches none of the current widgets is treated the same as
     * having never been set — see resolveOrder()'s isStale() doc comment.
     *
     * @param list<string>|null $savedWide
     * @return list<string>
     */
    public static function resolveWide(?array $savedWide): array
    {
        if ($savedWide === null || self::isStale($savedWide)) {
            return array_values(array_filter(array_keys(self::WIDGETS), fn (string $key): bool => self::isWide($key)));
        }

        return array_values(array_filter($savedWide, fn (string $key): bool => self::exists($key)));
    }
}
