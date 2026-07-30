<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One icon per Settings sub-page, keyed the same way _nav.php's $active
 * keys are — shared between the sub-nav (small, next to the label) and
 * each page's own header (larger, next to the title), so clicking a nav
 * item and landing on its page feels like the same place.
 */
final class SettingsIcons
{
    private const ICONS = [
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"/>',
        'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'household' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'categories' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'rules' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'tags' => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
    ];

    /**
     * Returns a self-contained <svg> (no color baked in — set via the
     * wrapping element's `color`/`currentColor`), or a generic circle
     * outline for any key not in the map above.
     */
    public static function svg(string $key): string
    {
        $inner = self::ICONS[$key] ?? '<circle cx="12" cy="12" r="9"/>';

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}
