<?php

/** @var list<array{label: string, income: string, expense: string, transfer: string, net: string, count: int}> $rows */
/** @var array{income: string, expense: string, transfer: string, net: string, count: int} $totals */
/** @var string $groupBy */
/** @var string|null $sort */
/** @var string $dir */
/** @var array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, tag_ids: list<int>, type: string, user_id: int|null} $filters */
/** @var array $accounts */
/** @var array $categories */
/** @var array $members */
/** @var array $tags */
/** @var string $csrfToken */

use App\Support\Money;
use App\Support\View;

$groupLabels = [
    'category' => 'Category',
    'merchant' => 'Merchant',
    'account' => 'Account',
    'month' => 'Month',
    'member' => 'Household member',
];

$exportQuery = http_build_query([
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'account_ids' => $filters['account_ids'],
    'category_ids' => $filters['category_ids'],
    'tag_ids' => $filters['tag_ids'],
    'type' => $filters['type'],
    'user_id' => $filters['user_id'] ?? '',
    'group_by' => $groupBy,
    'sort' => $sort ?? '',
    'dir' => $dir,
]);

/**
 * Quick date-range presets — before this, generating a report for "this
 * month" or "year to date" meant typing both dates by hand every time.
 * Each preset is a plain link (preserves every other active filter) that
 * lands exactly on the dates it claims, so the currently-active preset
 * can be detected by comparing against $filters and highlighted below.
 */
$today = gmdate('Y-m-d');
$presets = [
    'this_month' => ['label' => 'This Month', 'from' => gmdate('Y-m-01'), 'to' => $today],
    'last_month' => ['label' => 'Last Month', 'from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('last day of last month'))],
    'last_3_months' => ['label' => 'Last 3 Months', 'from' => date('Y-m-01', strtotime('-2 months')), 'to' => $today],
    'last_6_months' => ['label' => 'Last 6 Months', 'from' => date('Y-m-01', strtotime('-5 months')), 'to' => $today],
    'ytd' => ['label' => 'Year to Date', 'from' => gmdate('Y-01-01'), 'to' => $today],
    'last_12_months' => ['label' => 'Last 12 Months', 'from' => date('Y-m-01', strtotime('-11 months')), 'to' => $today],
    'all_time' => ['label' => 'All Time', 'from' => '1970-01-01', 'to' => $today],
];

$presetLink = function (array $preset) use ($filters, $groupBy, $sort, $dir): string {
    return '?' . http_build_query([
        ...$filters,
        'date_from' => $preset['from'],
        'date_to' => $preset['to'],
        'group_by' => $groupBy,
        'sort' => $sort ?? '',
        'dir' => $dir,
    ]);
};

$activePreset = null;
foreach ($presets as $key => $preset) {
    if ($preset['from'] === $filters['date_from'] && $preset['to'] === $filters['date_to']) {
        $activePreset = $key;
        break;
    }
}

$sortLink = function (string $column) use ($filters, $groupBy, $sort, $dir): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

    return '?' . http_build_query([
        ...$filters,
        'group_by' => $groupBy,
        'sort' => $column,
        'dir' => $nextDir,
    ]);
};

$sortIndicator = function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '';
    }

    return $dir === 'asc' ? ' &uarr;' : ' &darr;';
};

// Bar chart: income vs. expenses per group row. Capped to the first 15
// rows (already sorted by activity or the user's chosen column) so a
// report with dozens of merchants doesn't render an unreadable wall of
// bars — the full, un-capped breakdown is still in the table below.
$chartRows = array_slice($rows, 0, 15);
$chartPayload = [
    'labels' => array_column($chartRows, 'label'),
    'datasets' => [
        ['label' => 'Income', 'data' => array_map(fn (array $r): float => (float) $r['income'], $chartRows)],
        ['label' => 'Expenses', 'data' => array_map(fn (array $r): float => abs((float) $r['expense']), $chartRows)],
    ],
];

// The interactive filter form is hidden when printing (.no-print) since
// its <select>/<input> controls aren't meaningful on paper — this
// renders the same filters as plain text instead, so a printed report
// still says what it covers.
$memberName = null;
if ($filters['user_id'] !== null) {
    foreach ($members as $member) {
        if ((int) $member['id'] === $filters['user_id']) {
            $memberName = $member['name'];
            break;
        }
    }
}

$filterSummaryParts = [
    'Group by ' . $groupLabels[$groupBy],
    'Type: ' . ($filters['type'] !== '' ? ucfirst($filters['type']) : 'All'),
    'Member: ' . ($memberName ?? 'Everyone'),
];
if ($filters['account_ids'] !== []) {
    $filterSummaryParts[] = count($filters['account_ids']) . ' account' . (count($filters['account_ids']) === 1 ? '' : 's') . ' selected';
}
if ($filters['category_ids'] !== []) {
    $filterSummaryParts[] = count($filters['category_ids']) . ' categor' . (count($filters['category_ids']) === 1 ? 'y' : 'ies') . ' selected';
}
if ($filters['tag_ids'] !== []) {
    $filterSummaryParts[] = count($filters['tag_ids']) . ' tag' . (count($filters['tag_ids']) === 1 ? '' : 's') . ' selected';
}

$netPositive = bccomp($totals['net'], '0.00', 2) >= 0;
$arrowUpIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
$arrowDownIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
$transferIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>';
$listIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'reports']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-end justify-between no-print">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Reports</h1>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Filter and group your transactions any way you need.</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="window.print()" class="btn-secondary">Print report</button>
                        <a href="/reports/export?<?= $exportQuery ?>" class="btn-secondary">Export CSV</a>
                    </div>
                </div>

                <div class="print-only mb-6">
                    <h1 class="text-2xl font-semibold text-stone-900">MyCFO+ Report</h1>
                    <p class="text-sm text-stone-600"><?= View::e($filters['date_from']) ?> to <?= View::e($filters['date_to']) ?></p>
                    <p class="text-sm text-stone-600"><?= View::e(implode(' · ', $filterSummaryParts)) ?></p>
                    <p class="text-xs text-stone-500 mt-1">Printed <?= View::e(date('Y-m-d')) ?></p>
                </div>

                <form method="GET" action="/reports" class="card space-y-4 no-print">
                    <div>
                        <div class="field-label mb-1.5">Quick range</div>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($presets as $key => $preset): ?>
                                <a href="<?= View::e($presetLink($preset)) ?>" class="<?= $activePreset === $key ? 'badge-owner' : 'badge hover:bg-stone-200 dark:hover:bg-stone-700 transition-colors' ?>"><?= View::e($preset['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label for="date_from" class="field-label">From</label>
                            <input type="date" id="date_from" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="date_to" class="field-label">To</label>
                            <input type="date" id="date_to" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="field-input">
                        </div>
                        <div>
                            <label for="type" class="field-label">Type</label>
                            <select id="type" name="type" class="field-input">
                                <option value="" <?= $filters['type'] === '' ? 'selected' : '' ?>>All</option>
                                <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                <option value="transfer" <?= $filters['type'] === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label for="user_id" class="field-label">Member</label>
                            <select id="user_id" name="user_id" class="field-input">
                                <option value="">Everyone</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= (int) $member['id'] ?>" <?= $filters['user_id'] === (int) $member['id'] ? 'selected' : '' ?>><?= View::e($member['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="group_by" class="field-label">Group by</label>
                            <select id="group_by" name="group_by" class="field-input">
                                <?php foreach ($groupLabels as $key => $label): ?>
                                    <option value="<?= View::e($key) ?>" <?= $groupBy === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary w-full">Apply</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label for="account_ids" class="field-label">Accounts (leave empty for all)</label>
                            <select id="account_ids" name="account_ids[]" multiple class="field-input" size="4">
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>" <?= in_array((int) $account['id'], $filters['account_ids'], true) ? 'selected' : '' ?>><?= View::e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="category_ids" class="field-label">Categories (leave empty for all)</label>
                            <select id="category_ids" name="category_ids[]" multiple class="field-input" size="4">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $filters['category_ids'], true) ? 'selected' : '' ?>><?= View::e($category['name']) ?> (<?= View::e(ucfirst($category['type'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="tag_ids" class="field-label">Tags (leave empty for all)</label>
                            <?php if ($tags === []): ?>
                                <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">No tags yet — <a href="/settings/tags" class="text-terracotta-600 dark:text-terracotta-400 hover:underline">create one</a>.</p>
                            <?php else: ?>
                                <select id="tag_ids" name="tag_ids[]" multiple class="field-input" size="4">
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?= (int) $tag['id'] ?>" <?= in_array((int) $tag['id'], $filters['tag_ids'], true) ? 'selected' : '' ?>><?= View::e($tag['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="stat-hero <?= $netPositive ? 'stat-hero-positive' : 'stat-hero-negative' ?> flex items-center justify-between flex-wrap gap-6">
                    <span class="stat-hero-icon <?= $netPositive ? 'text-emerald-600' : 'text-red-600' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div style="position: relative;">
                        <div class="text-xs font-bold uppercase tracking-wide <?= $netPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?> mb-2">Net &middot; Filtered results</div>
                        <div class="text-stone-900 dark:text-white" style="font-size: 2.75rem; line-height: 1; font-weight: 600; letter-spacing: -0.02em;"><?= Money::format($totals['net']) ?></div>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">Income minus expenses across the filters above. Transfers aren't counted as income or spending.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(52, 211, 153, .14); color: #059669;"><?= $arrowUpIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Income</div>
                        </div>
                        <div class="text-xl font-semibold text-emerald-700 dark:text-emerald-400"><?= Money::format($totals['income']) ?></div>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(226, 105, 75, .14); color: #c94f32;"><?= $arrowDownIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Expenses</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= Money::format($totals['expense']) ?></div>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $transferIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Transfers</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-500 dark:text-stone-400"><?= Money::format($totals['transfer']) ?></div>
                    </div>
                    <div class="card">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="metric-icon" style="background: rgba(168, 162, 158, .16); color: #78716c;"><?= $listIcon ?></span>
                            <div class="text-xs font-bold uppercase tracking-wide text-stone-900 dark:text-white">Transactions</div>
                        </div>
                        <div class="text-xl font-semibold text-stone-900 dark:text-white"><?= $totals['count'] ?></div>
                    </div>
                </div>

                <?php if ($rows === []): ?>
                    <div class="card text-center py-12">
                        <p class="text-stone-500 dark:text-stone-400">No transactions match these filters.</p>
                    </div>
                <?php else: ?>
                    <div class="card no-print">
                        <div style="height: 280px;">
                            <canvas id="chart-report" data-chart="bar" aria-label="Income and expenses per <?= View::e(strtolower($groupLabels[$groupBy])) ?>" role="img"></canvas>
                        </div>
                        <script type="application/json" id="chart-report-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                        <?php if (count($rows) > count($chartRows)): ?>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-2">Showing the top <?= count($chartRows) ?> of <?= count($rows) ?> rows — the full breakdown is in the table below.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th><a href="<?= View::e($sortLink('label')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200"><?= View::e($groupLabels[$groupBy]) ?><?= $sortIndicator('label') ?></a></th>
                                    <th class="text-right"><a href="<?= View::e($sortLink('income')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Income<?= $sortIndicator('income') ?></a></th>
                                    <th class="text-right"><a href="<?= View::e($sortLink('expense')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Expenses<?= $sortIndicator('expense') ?></a></th>
                                    <th class="text-right"><a href="<?= View::e($sortLink('transfer')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Transfers<?= $sortIndicator('transfer') ?></a></th>
                                    <th class="text-right"><a href="<?= View::e($sortLink('net')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Net<?= $sortIndicator('net') ?></a></th>
                                    <th class="text-right"><a href="<?= View::e($sortLink('count')) ?>" class="hover:text-stone-700 dark:hover:text-stone-200">Count<?= $sortIndicator('count') ?></a></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="font-medium text-stone-900 dark:text-white"><?= View::e($row['label']) ?></td>
                                        <td class="text-right text-emerald-700 dark:text-emerald-400"><?= Money::format($row['income']) ?></td>
                                        <td class="text-right"><?= Money::format($row['expense']) ?></td>
                                        <td class="text-right text-stone-500 dark:text-stone-400"><?= Money::format($row['transfer']) ?></td>
                                        <td class="text-right font-medium <?= bccomp($row['net'], '0.00', 2) < 0 ? 'text-red-600 dark:text-red-400' : 'text-stone-900 dark:text-white' ?>"><?= Money::format($row['net']) ?></td>
                                        <td class="text-right text-stone-500 dark:text-stone-400"><?= $row['count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="<?= View::asset('/assets/js/vendor/chart.umd.min.js') ?>" defer></script>
    <script src="<?= View::asset('/assets/js/charts.js') ?>" defer></script>
</body>
</html>
