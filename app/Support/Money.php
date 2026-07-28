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

    /**
     * An abbreviated form ($4.8K, $1.2M) for tight spaces like a donut
     * chart's center label, where the full 2-decimal format doesn't fit.
     */
    public static function formatCompact(?string $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        $isNegative = str_starts_with($amount, '-');
        $abs = (float) ltrim($amount, '-');

        if ($abs >= 1_000_000) {
            $formatted = '$' . number_format($abs / 1_000_000, 1) . 'M';
        } elseif ($abs >= 1_000) {
            $formatted = '$' . number_format($abs / 1_000, 1) . 'K';
        } else {
            $formatted = '$' . number_format($abs, 0);
        }

        return $isNegative ? '-' . $formatted : $formatted;
    }
}
