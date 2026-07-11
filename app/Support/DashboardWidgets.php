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
        'accounts' => ['title' => 'Accounts', 'available' => true],
        'spending' => ['title' => 'Spending', 'available' => false],
        'budget' => ['title' => 'Budget', 'available' => false],
        'transactions' => ['title' => 'Recent Transactions', 'available' => false],
        'recurring' => ['title' => 'Recurring Bills', 'available' => false],
        'goals' => ['title' => 'Goals', 'available' => false],
        'investments' => ['title' => 'Investments', 'available' => false],
    ];

    /** @var list<string> */
    private const DEFAULT_ORDER = [
        'net_worth', 'accounts', 'spending', 'budget',
        'transactions', 'recurring', 'goals', 'investments',
    ];

    public static function title(string $key): string
    {
        return self::WIDGETS[$key]['title'] ?? ucfirst($key);
    }

    public static function isAvailable(string $key): bool
    {
        return self::WIDGETS[$key]['available'] ?? false;
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
}
