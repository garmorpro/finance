<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    /**
     * Formats a DECIMAL string (never a float) for display only.
     */
    public static function format(?string $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        $isNegative = str_starts_with($amount, '-');
        $abs = ltrim($amount, '-');

        $formatted = '$' . number_format((float) $abs, 2);

        return $isNegative ? '-' . $formatted : $formatted;
    }
}
