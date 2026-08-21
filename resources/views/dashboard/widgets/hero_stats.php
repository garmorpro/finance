<?php

/**
 * Renders inside the fixed dark hero band (see dashboard/index.php).
 * Net Worth, Runway, Savings Rate, and Cash Flow used to be four
 * independently draggable/hideable "stats" tiles; the Overview page
 * has no customization left at all now, and these four are merged into
 * one always-visible block here instead.
 *
 * @var array|null $netWorth
 * @var list<array{label: string, assets: string, liabilities: string, netWorth: string}> $netWorthTrend
 * @var float|null $runwayMonths
 * @var array{income: string, livingExpenses: string, debtPayments: string, savings: string, giving: string, remaining: string}|null $allocationSummary
 * @var array{label: string, income: string, expenses: string, net: string, cumulative: string}|null $thisMonthCashFlow
 */

use App\Services\ReportingService;
use App\Support\Money;

$netWorthChange = null;
if (count($netWorthTrend) >= 2) {
    $netWorthChange = bcsub($netWorthTrend[count($netWorthTrend) - 1]['netWorth'], $netWorthTrend[count($netWorthTrend) - 2]['netWorth'], 2);
}

$savingsRate = (new ReportingService())->savingsRate($allocationSummary);

// Sparkline: min/max-scale each trend point's net worth into a 400x120
// viewBox. Chart-data-to-markup math like this already lives directly
// in income_by_source.php's conic-gradient stops, so this follows the
// same convention rather than introducing a separate charting layer
// for one sparkline.
$chartPoints = [];
if (count($netWorthTrend) >= 2) {
    $values = array_map(static fn (array $row): float => (float) $row['netWorth'], $netWorthTrend);
    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $count = count($values);

    foreach ($values as $i => $value) {
        $x = $count > 1 ? ($i / ($count - 1)) * 400 : 200;
        $y = $range > 0 ? 110 - (($value - $min) / $range) * 100 : 60;
        $chartPoints[] = round($x, 1) . ',' . round($y, 1);
    }
}
$chartPath = implode(' L', $chartPoints);
$lastPoint = $chartPoints === [] ? null : explode(',', end($chartPoints));

?>
<div class="dash-hero-nw">
    <p class="dash-hero-eyebrow">Net Worth</p>
    <div class="dash-hero-nw-value tabular-nums"><?= $netWorth === null ? '—' : Money::format($netWorth['net']) ?></div>

    <?php if ($netWorthChange !== null): ?>
        <div class="dash-hero-nw-foot">
            <span class="dash-hero-pill <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? 'up' : 'down' ?>">
                <?php if (bccomp($netWorthChange, '0.00', 2) >= 0): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16l6-8 6 8"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8l6 8 6-8"/></svg>
                <?php endif; ?>
                <?= bccomp($netWorthChange, '0.00', 2) >= 0 ? '+' : '' ?><?= Money::format($netWorthChange) ?>
            </span>
            <span class="dash-hero-nw-caption">this month</span>
        </div>
    <?php else: ?>
        <p class="dash-hero-nw-caption" style="margin-top:0.75rem;">Not enough history yet</p>
    <?php endif; ?>

    <?php if ($chartPoints !== []): ?>
        <svg class="dash-hero-chart" viewBox="0 0 400 120" preserveAspectRatio="none" role="img" aria-label="Net worth trend over the past <?= count($netWorthTrend) ?> months">
            <defs>
                <linearGradient id="dashHeroNwFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#e2694b" stop-opacity="0.38"/>
                    <stop offset="100%" stop-color="#e2694b" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <line x1="0" y1="30" x2="400" y2="30" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
            <line x1="0" y1="70" x2="400" y2="70" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
            <line x1="0" y1="110" x2="400" y2="110" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
            <path d="M<?= $chartPath ?> L400,120 L0,120 Z" fill="url(#dashHeroNwFill)"/>
            <path d="M<?= $chartPath ?>" fill="none" stroke="#f0a488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <?php if ($lastPoint !== null): ?>
                <circle cx="<?= $lastPoint[0] ?>" cy="<?= $lastPoint[1] ?>" r="4.5" fill="#1c1917" stroke="#f0a488" stroke-width="2"/>
            <?php endif; ?>
        </svg>
    <?php endif; ?>
</div>

<div class="dash-hero-stats">
    <div class="dash-hero-stat">
        <span class="dash-hero-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
        </span>
        <span>
            <span class="dash-hero-stat-label">Runway</span>
            <span class="dash-hero-stat-value tabular-nums"><?= $runwayMonths === null ? '—' : number_format($runwayMonths, 1) . ' months' ?></span>
        </span>
    </div>
    <div class="dash-hero-stat">
        <span class="dash-hero-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </span>
        <span>
            <span class="dash-hero-stat-label">Savings Rate</span>
            <span class="dash-hero-stat-value tabular-nums"><?= $savingsRate === null ? '—' : number_format($savingsRate, 0) . '%' ?></span>
        </span>
    </div>
    <div class="dash-hero-stat">
        <span class="dash-hero-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/></svg>
        </span>
        <span>
            <span class="dash-hero-stat-label">Cash Flow</span>
            <span class="dash-hero-stat-value tabular-nums"><?= $thisMonthCashFlow === null ? '—' : (bccomp($thisMonthCashFlow['net'], '0.00', 2) >= 0 ? '+' : '') . Money::format($thisMonthCashFlow['net']) ?></span>
        </span>
    </div>
</div>
