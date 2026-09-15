<?php

/** @var array $entries */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var array{date_from: string, date_to: string} $filters */
/** @var string $csrfToken */

use App\Support\AuditActionLabels;
use App\Support\LocalTime;
use App\Support\SettingsIcons;
use App\Support\View;

$totalPages = max(1, (int) ceil($total / $perPage));

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
                                Security and activity history for your household — sign-ins, transaction changes, settings updates, and everything in between. Owner-only. <?= $total ?> event<?= $total === 1 ? '' : 's' ?> total.
                            </p>
                        </div>

                        <form method="GET" action="/settings/audit-log" class="card flex flex-wrap items-end gap-3">
                            <div>
                                <label for="f-from" class="field-label">From</label>
                                <input type="date" id="f-from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input">
                            </div>
                            <div>
                                <label for="f-to" class="field-label">To</label>
                                <input type="date" id="f-to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input">
                            </div>
                            <button type="submit" class="btn-primary">Filter</button>
                            <?php if ($filters['date_from'] !== '' || $filters['date_to'] !== ''): ?>
                                <a href="/settings/audit-log" class="filter-clear">Clear</a>
                            <?php endif; ?>
                        </form>

                        <?php if ($entries === []): ?>
                            <div class="card text-center py-12">
                                <p class="text-stone-500 dark:text-stone-400">No activity in this range.</p>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <table class="table-base">
                                    <thead>
                                        <tr>
                                            <th>When</th>
                                            <th>Event</th>
                                            <th>Who</th>
                                            <th>IP address</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($entries as $entry): ?>
                                            <?php
                                            $metadata = $entry['metadata'] !== null ? json_decode((string) $entry['metadata'], true) : null;
                                            $metadataText = is_array($metadata) && $metadata !== []
                                                ? implode(', ', array_map(
                                                    static fn ($key, $value): string => $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value)),
                                                    array_keys($metadata),
                                                    $metadata
                                                ))
                                                : null;
                                            ?>
                                            <tr>
                                                <td class="text-stone-500 dark:text-stone-400 whitespace-nowrap"><?= LocalTime::html($entry['created_at']) ?></td>
                                                <td class="font-medium text-stone-900 dark:text-white"><?= View::e(AuditActionLabels::label($entry['action'])) ?></td>
                                                <td class="text-stone-500 dark:text-stone-400">
                                                    <?= $entry['user_name'] !== null ? View::e($entry['user_name']) : '—' ?>
                                                </td>
                                                <td class="text-stone-500 dark:text-stone-400 whitespace-nowrap"><?= $entry['ip_address'] !== null ? View::e($entry['ip_address']) : '—' ?></td>
                                                <td class="text-xs text-stone-400 dark:text-stone-600"><?= $metadataText !== null ? View::e($metadataText) : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
