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
    private const WIDGETS = [
        'net_worth' => ['title' => 'Net Worth', 'available' => true],
        'net_worth_trend' => ['title' => 'Net Worth Trend', 'available' => true, 'wide' => true],
        'accounts' => ['title' => 'Accounts', 'available' => true],
        'spending' => ['title' => 'Spending by Category', 'available' => true, 'wide' => true],
        'income_vs_spending' => ['title' => 'Income vs. Spending', 'available' => true, 'wide' => true],
        'budget' => ['title' => 'Budget', 'available' => true],
        'transactions' => ['title' => 'Recent Transactions', 'available' => true],
        'review' => ['title' => 'Needs Review', 'available' => true],
        'recurring' => ['title' => 'Recurring Bills', 'available' => true],
        'goals' => ['title' => 'Goals', 'available' => false],
        'investments' => ['title' => 'Investments', 'available' => false],
    ];

    /** @var list<string> */
    private const DEFAULT_ORDER = [
        'net_worth', 'net_worth_trend', 'accounts', 'spending', 'income_vs_spending', 'budget',
        'transactions', 'review', 'recurring', 'goals', 'investments',
    ];

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
     * them, and any stale/unknown keys in a saved layout are dropped.
     *
     * @param list<string> $savedOrder
     * @return list<string>
     */
    public static function resolveOrder(array $savedOrder): array
    {
        $known = array_values(array_filter($savedOrder, fn (string $key): bool => self::exists($key)));
        $missing = array_values(array_diff(self::DEFAULT_ORDER, $known));

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
     * set), otherwise each widget's own built-in default.
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
