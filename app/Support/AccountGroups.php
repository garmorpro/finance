<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Groups accounts into display sections for the Accounts page — Cash,
 * Credit Cards, Loans, Investments, Property & Other — each with a
 * subtotal. Purely a presentation grouping; it has no bearing on net
 * worth or any other calculation, which stay driven entirely by
 * AccountRepository's own asset/liability logic.
 */
final class AccountGroups
{
    private const GROUPS = [
        'Cash' => ['checking', 'savings', 'cash'],
        'Credit Cards' => ['credit_card'],
        'Loans' => ['mortgage', 'auto_loan', 'student_loan', 'personal_loan', 'other_liability'],
        'Investments' => ['investment', 'retirement'],
        'Property & Other Assets' => ['property', 'vehicle', 'other_asset'],
    ];

    /**
     * @param list<array<string, mixed>> $accounts
     * @return list<array{label: string, accounts: list<array<string, mixed>>, total: string}>
     */
    public static function group(array $accounts): array
    {
        $sections = [];
        $covered = [];

        foreach (self::GROUPS as $label => $types) {
            $matching = array_values(array_filter(
                $accounts,
                fn (array $a): bool => in_array($a['account_type'], $types, true)
            ));

            if ($matching === []) {
                continue;
            }

            $sections[] = ['label' => $label, 'accounts' => $matching, 'total' => self::sumBalances($matching)];
            $covered = [...$covered, ...$types];
        }

        // Anything not covered above (shouldn't happen given
        // AccountRepository::TYPES, but keeps a future new account type
        // from silently vanishing off this page if GROUPS isn't updated
        // alongside it).
        $uncovered = array_values(array_filter(
            $accounts,
            fn (array $a): bool => !in_array($a['account_type'], $covered, true)
        ));

        if ($uncovered !== []) {
            $sections[] = ['label' => 'Other', 'accounts' => $uncovered, 'total' => self::sumBalances($uncovered)];
        }

        return $sections;
    }

    /**
     * @param list<array<string, mixed>> $accounts
     */
    private static function sumBalances(array $accounts): string
    {
        $total = '0.00';
        foreach ($accounts as $account) {
            $total = bcadd($total, $account['current_balance'], 2);
        }

        return $total;
    }
}
