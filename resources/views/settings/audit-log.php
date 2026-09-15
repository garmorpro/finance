<?php

/** @var array $entries */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array{date_from: string, date_to: string, category: string} $filters */
/** @var string $csrfToken */

use App\Support\AuditActionLabels;
use App\Support\LocalTime;
use App\Support\SettingsIcons;
use App\Support\View;

$totalPages = max(1, (int) ceil($total / $perPage));

$initialsFor = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false || $parts === []) {
        return '?';
    }
    return strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
};

// Same weekday/month/day convention as the Transactions page's own
// day-grouping — a full year is only spelled out when it isn't the
// current one, since "Monday, Sep 15" is unambiguous for anything
// recent and a bare date range rarely spans a year boundary.
$currentYear = gmdate('Y');
$dayLabel = static function (string $datetime) use ($currentYear): string {
    $timestamp = strtotime($datetime);
    $format = date('Y', $timestamp) === $currentYear ? 'l, M j' : 'l, M j, Y';

    return date($format, $timestamp);
};

$chipUrl = static function (?string $category) use ($filters): string {
    return '?' . http_build_query([...$filters, 'category' => $category ?? '', 'page' => 1]);
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <?php View::partial('partials/_pwa_head'); ?>
    <title>Audit Log · Settings · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'settings']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white mb-6">Settings</h1>

                <div class="settings-layout">
                    <?php View::partial('settings/_nav', ['active' => 'audit-log']); ?>

                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-stone-400 dark:text-stone-500"><?= SettingsIcons::svg('audit-log') ?></span>
                                <h2 class="text-lg font-semibold text-stone-900 dark:text-white">Audit Log</h2>
                            </div>
                            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                                Every sign-in, security event, and change to your household, grouped by day. Owner-only — <?= $total ?> event<?= $total === 1 ? '' : 's' ?> total.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= View::e($chipUrl(null)) ?>" class="<?= $filters['category'] === '' ? 'audit-filter-chip-active' : 'audit-filter-chip' ?>">All activity</a>
                                <a href="<?= View::e($chipUrl('security')) ?>" class="<?= $filters['category'] === 'security' ? 'audit-filter-chip-active' : 'audit-filter-chip' ?>">Security</a>
                                <a href="<?= View::e($chipUrl('quick_add_key')) ?>" class="<?= $filters['category'] === 'quick_add_key' ? 'audit-filter-chip-active' : 'audit-filter-chip' ?>">Quick Add key</a>
                            </div>

                            <form method="GET" action="/settings/audit-log" class="flex flex-wrap items-end gap-2">
                                <input type="hidden" name="category" value="<?= View::e($filters['category']) ?>">
                                <div>
                                    <label for="f-from" class="sr-only">From</label>
                                    <input type="date" id="f-from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input" style="padding-top:.4rem;padding-bottom:.4rem;">
                                </div>
                                <span class="text-stone-400 dark:text-stone-600 text-sm">&ndash;</span>
                                <div>
                                    <label for="f-to" class="sr-only">To</label>
                                    <input type="date" id="f-to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input" style="padding-top:.4rem;padding-bottom:.4rem;">
                                </div>
                                <button type="submit" class="btn-secondary">Filter</button>
                                <?php if ($filters['date_from'] !== '' || $filters['date_to'] !== ''): ?>
                                    <a href="<?= View::e($chipUrl($filters['category'] !== '' ? $filters['category'] : null)) ?>" class="filter-clear">Clear dates</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php if ($entries === []): ?>
                            <div class="card text-center py-12">
                                <p class="text-stone-500 dark:text-stone-400">No activity in this range.</p>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="audit-timeline">
                                    <?php $currentDay = null; ?>
                                    <?php foreach ($entries as $entry): ?>
                                        <?php
                                        $day = substr((string) $entry['created_at'], 0, 10);
                                        if ($day !== $currentDay):
                                            $currentDay = $day;
                                        ?>
                                            <p class="audit-day-label"><?= View::e($dayLabel($entry['created_at'])) ?></p>
                                        <?php endif; ?>
                                        <?php
                                        $metadata = $entry['metadata'] !== null ? json_decode((string) $entry['metadata'], true) : null;
                                        $metadataText = is_array($metadata) && $metadata !== []
                                            ? implode(' · ', array_map(
                                                static fn ($key, $value): string => $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value)),
                                                array_keys($metadata),
                                                $metadata
                                            ))
                                            : null;
                                        ?>
                                        <?php
                                        $dotColor = AuditActionLabels::dotColor($entry['action']);
                                        $dotClass = $dotColor !== 'routine' ? ' audit-event-' . $dotColor : '';
                                        ?>
                                        <div class="audit-event<?= $dotClass ?>">
                                            <div class="audit-event-row">
                                                <span class="audit-event-title"><?= View::e(AuditActionLabels::label($entry['action'])) ?></span>
                                                <span class="audit-event-time"><?= LocalTime::html($entry['created_at']) ?></span>
                                            </div>
                                            <div class="audit-event-meta">
                                                <?php if ($entry['user_name'] !== null): ?>
                                                    <span class="audit-avatar"><?= View::e($initialsFor($entry['user_name'])) ?></span>
                                                    <span class="text-xs text-stone-500 dark:text-stone-400"><?= View::e($entry['user_name']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($entry['ip_address'] !== null): ?>
                                                    <span class="audit-ip-chip"><?= View::e($entry['ip_address']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($metadataText !== null): ?>
                                                <p class="audit-event-detail"><?= View::e($metadataText) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($totalPages > 1): ?>
                            <div class="flex items-center justify-between text-sm text-stone-500 dark:text-stone-400">
                                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                                <div class="flex gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query([...$filters, 'page' => $page - 1]) ?>" class="btn-secondary">Previous</a>
                                    <?php endif; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query([...$filters, 'page' => $page + 1]) ?>" class="btn-secondary">Next</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
