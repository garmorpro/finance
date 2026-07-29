<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small line icon per Budgets category-group header, picked by keyword
 * match against the group's own (free-text, user-chosen) name — unlike
 * account types, category groups have no fixed enum to key off, so this
 * degrades gracefully to a generic icon for anything unmatched rather
 * than guessing wrong.
 */
final class CategoryGroupIcons
{
    /** @var list<array{keywords: list<string>, icon: string}> */
    private const RULES = [
        ['keywords' => ['income', 'salary', 'paycheck', 'wage', 'interest', 'refund', 'side', 'bonus'], 'icon' => 'cash'],
        ['keywords' => ['housing', 'rent', 'mortgage'], 'icon' => 'home'],
        ['keywords' => ['utilit', 'electric', 'gas', 'water', 'power'], 'icon' => 'bolt'],
        ['keywords' => ['grocer', 'food', 'dining', 'restaurant', 'shopping', 'cloth'], 'icon' => 'bag'],
        ['keywords' => ['transport', 'auto', 'car', 'vehicle', 'fuel'], 'icon' => 'car'],
        ['keywords' => ['insurance'], 'icon' => 'shield'],
        ['keywords' => ['health', 'medical', 'dental'], 'icon' => 'heart'],
        ['keywords' => ['entertain', 'subscript', 'streaming', 'fun'], 'icon' => 'play'],
        ['keywords' => ['travel', 'vacation'], 'icon' => 'plane'],
        ['keywords' => ['debt', 'loan', 'payment'], 'icon' => 'card'],
        ['keywords' => ['saving', 'invest'], 'icon' => 'trending-up'],
        ['keywords' => ['gift', 'donat', 'giving', 'charit'], 'icon' => 'gift'],
        ['keywords' => ['education', 'tuition', 'school'], 'icon' => 'graduation-cap'],
        ['keywords' => ['child', 'kid', 'baby'], 'icon' => 'smile'],
    ];

    private const ICONS = [
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'home' => '<path d="M3 9l9-7 9 7"/><polyline points="9 22 9 12 15 12 15 22"/><path d="M4 10v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10"/>',
        'bolt' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'car' => '<path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5"/><path d="M3 13v5a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-5"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
        'play' => '<rect x="2" y="3" width="20" height="14" rx="2"/><polygon points="10 8 15 10.5 10 13 10 8"/><line x1="8" y1="21" x2="16" y2="21"/>',
        'plane' => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
        'card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'trending-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'gift' => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        'graduation-cap' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
        'smile' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    ];

    /**
     * Returns a self-contained <svg> (no color baked in — set via the
     * wrapping element's `color`/`currentColor`).
     */
    public static function forName(?string $groupName): string
    {
        $icon = 'folder';

        if ($groupName !== null) {
            $haystack = mb_strtolower($groupName);
            foreach (self::RULES as $rule) {
                foreach ($rule['keywords'] as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $icon = $rule['icon'];
                        break 2;
                    }
                }
            }
        }

        $inner = self::ICONS[$icon];

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}
