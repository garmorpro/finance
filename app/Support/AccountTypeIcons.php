<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small line-icon per account type for the Accounts page. Icons are
 * intentionally shared across closely-related types (mortgage/property
 * both use a house, auto_loan/vehicle both use a car) — the point is a
 * quick visual category, not a unique glyph per type.
 */
final class AccountTypeIcons
{
    private const ICONS = [
        'checking' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
        'savings' => '<path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 1 3 2 3.5V17l2 2h2v-1.5l1-.5h2l1 .5V19h2l2-2v-1c1-.5 2-1.5 2-3.5 0-1-.5-2-1.5-2.5"/><circle cx="7" cy="10" r="1"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'credit_card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'mortgage' => '<path d="M3 9l9-7 9 7"/><polyline points="9 22 9 12 15 12 15 22"/><path d="M4 10v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10"/>',
        'auto_loan' => '<path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5"/><path d="M3 13v5a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-5"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
        'student_loan' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
        'personal_loan' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'investment' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'retirement' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'property' => '<path d="M3 9l9-7 9 7"/><polyline points="9 22 9 12 15 12 15 22"/><path d="M4 10v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10"/>',
        'vehicle' => '<path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5"/><path d="M3 13v5a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-5"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
        'other_asset' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'other_liability' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    ];

    /**
     * Returns a self-contained <svg> (no color baked in — set via the
     * wrapping element's `color`/`currentColor`), or a generic circle
     * outline for any type not in the map above.
     */
    public static function svg(string $type): string
    {
        $inner = self::ICONS[$type] ?? '<circle cx="12" cy="12" r="9"/>';

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}
