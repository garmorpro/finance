<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Validates and normalizes user-supplied monetary input server-side.
 * Never trust client-calculated totals — this is the single place
 * request strings become the DECIMAL-safe values the database expects.
 */
final class MoneyInput
{
    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', trim($value));
    }

    public static function normalize(string $value): string
    {
        return number_format((float) trim($value), 2, '.', '');
    }

    public static function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : self::normalize($value);
    }
}
