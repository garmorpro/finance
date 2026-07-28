<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registry of dashboard tiles. `available` widgets render real data;
 * unavailable ones render an honest "coming soon" placeholder rather
 * than fabricated numbers, until their feature phase is built.
 *
 * Widgets belong to one of two independently-orderable groups:
 * - `stats`: the four single-number cards, laid out in a fixed 4-up row
 *   (or fewer columns on narrower screens) — dragged and reordered only
 *   among themselves.
 * - `main`: the larger chart/list tiles, laid out in the existing
 *   draggable/resizable masonry grid — also reordered only among
 *   themselves, never mixed with `stats`.
 *
 * "The Lifecycle of a Dollar" is deliberately not in this registry at
 * all — it's rendered as a fixed sticky sidebar panel next to the main
 * grid (see dashboard/index.php), not a draggable/hideable tile.
 */
final class DashboardWidgets
{
    private const WIDGETS = [
        'runway' => ['title' => 'Runway', 'group' => 'stats', 'available' => true],
        'savings_rate' => ['title' => 'Savings Rate', 'group' => 'stats', 'available' => true],
        'net_worth' => ['title' => 'Net Worth', 'group' => 'stats', 'available' => true],
        'cash_flow' => ['title' => 'Cash Flow This Month', 'group' => 'stats', 'available' => true],
        'capital_allocation' => ['title' => 'Capital Allocation', 'group' => 'main', 'available' => true],
        'income_by_source' => ['title' => 'Income by Source', 'group' => 'main', 'available' => true],
        'top_expense_categories' => ['title' => 'Top Expense Categories', 'group' => 'main', 'available' => true],
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

    public static function group(string $key): string
    {
        return self::WIDGETS[$key]['group'] ?? 'main';
    }

    /**
     * @return list<string>
     */
    public static function keysInGroup(string $group): array
    {
        return array_values(array_filter(array_keys(self::WIDGETS), fn (string $key): bool => self::group($key) === $group));
    }

    /**
     * All widget keys, in registry order — used by the Customize modal's
     * hide-toggle list, which spans both groups.
     *
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::WIDGETS);
    }

    /**
     * Merges a user's saved order (for one group only) with that group's
     * canonical widget list, so newly added widgets always appear even
     * if the user's saved layout predates them, and any stale/unknown/
     * wrong-group keys are dropped.
     *
     * @param list<string> $savedOrder
     * @return list<string>
     */
    public static function resolveOrder(string $group, array $savedOrder): array
    {
        $groupKeys = self::keysInGroup($group);
        $known = array_values(array_filter($savedOrder, fn (string $key): bool => in_array($key, $groupKeys, true)));
        $missing = array_values(array_diff($groupKeys, $known));

        return [...$known, ...$missing];
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
     * set), otherwise each widget's own built-in default. Only `main`
     * widgets ever have a resize control, but this stays generic over
     * the whole registry since a stale `stats` entry here is harmless
     * (nothing reads it).
     *
     * @param list<string>|null $savedWide
     * @return list<string>
     */
    public static function resolveWide(?array $savedWide): array
    {
        if ($savedWide === null) {
            return array_values(array_filter(array_keys(self::WIDGETS), fn (string $key): bool => self::isWide($key)));
        }

        return array_values(array_filter($savedWide, fn (string $key): bool => self::exists($key)));
    }
}
