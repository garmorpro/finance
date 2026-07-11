<?php

declare(strict_types=1);

namespace App\Support;

final class AccountTypeLabels
{
    private const LABELS = [
        'checking' => 'Checking',
        'savings' => 'Savings',
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'mortgage' => 'Mortgage',
        'auto_loan' => 'Auto Loan',
        'student_loan' => 'Student Loan',
        'personal_loan' => 'Personal Loan',
        'investment' => 'Investment',
        'retirement' => 'Retirement',
        'property' => 'Property',
        'vehicle' => 'Vehicle',
        'other_asset' => 'Other Asset',
        'other_liability' => 'Other Liability',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
