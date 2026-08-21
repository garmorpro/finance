<?php

/**
 * Renders directly in the main column of dashboard/index.php — a fixed
 * card, same convention as the hero and waterfall before it. See
 * App\Services\InsightsService for how this list is built.
 *
 * @var list<array{type: string, title: string, subtitle: string, link: array{label: string, href: string}|null}> $insights
 */

use App\Support\View;

// Full class names spelled out literally in this array (not built by
// string interpolation) on purpose — Tailwind's build only emits CSS
// for classes it can find as literal text while scanning this file, so
// an interpolated "insight-icon-{$type}" would compile clean but
// silently ship with no color at all, since the scanner can't see a
// class name assembled at request time. See resources/css/app.css's
// .insight-icon-warn/-positive/-info.
$iconMeta = [
    'warn' => [
        'class' => 'insight-icon-warn',
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ],
    'positive' => [
        'class' => 'insight-icon-positive',
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    ],
    'info' => [
        'class' => 'insight-icon-info',
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
    ],
];

?>
<div class="flex items-baseline justify-between flex-wrap gap-1 mb-4">
    <h2 class="text-base font-semibold text-stone-900 dark:text-white">Insights</h2>
    <p class="text-xs text-stone-500 dark:text-stone-400">What's worth knowing about this month</p>
</div>

<div class="insight-list">
    <?php foreach ($insights as $insight): ?>
        <?php $meta = $iconMeta[$insight['type']] ?? $iconMeta['info']; ?>
        <div class="insight-row">
            <span class="insight-icon <?= $meta['class'] ?>">
                <?= $meta['svg'] ?>
            </span>
            <div class="insight-main">
                <div class="insight-title"><?= View::e($insight['title']) ?></div>
                <div class="insight-sub"><?= View::e($insight['subtitle']) ?></div>
            </div>
            <?php if ($insight['link'] !== null): ?>
                <a class="insight-link" href="<?= View::e($insight['link']['href']) ?>"><?= View::e($insight['link']['label']) ?> &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
