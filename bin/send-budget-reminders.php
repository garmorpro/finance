<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\BudgetReviewLinkRepository;
use App\Repositories\HouseholdRepository;
use App\Services\BudgetReviewLinkService;
use App\Support\Logger;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Meant to run once a day via cron (see docs/deployment.md). For every
 * household with the budget planning reminder turned on (Settings ->
 * Household), issues a scoped magic link to next month's Budget Review
 * for each Owner/Administrator — once that household's own
 * "days before" trigger window opens, and once per household/user/month
 * (safe to run daily; already-issued links are never re-issued even if
 * a run is missed and this catches up a day or two late).
 *
 * Email sending is stubbed app-wide for now (see CLAUDE.md and
 * PasswordResetController/HouseholdController) — this logs each link
 * instead of delivering it, same as password resets and invitations,
 * until real SMTP is configured.
 *
 *   php bin/send-budget-reminders.php
 */

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$today = new DateTimeImmutable('today');
$nextMonthStart = $today->modify('first day of next month');
$nextMonthKey = $nextMonthStart->format('Y-m-d');

$householdRepo = new HouseholdRepository();
$linkRepo = new BudgetReviewLinkRepository();
$linkService = new BudgetReviewLinkService();
$auditLog = new AuditLogRepository();

$issued = 0;
$skippedAlreadyIssued = 0;
$householdsInWindow = 0;

foreach ($householdRepo->listWithBudgetReminderEnabled() as $household) {
    $daysBefore = (int) $household['budget_reminder_days_before'];
    $triggerDate = $nextMonthStart->modify("-{$daysBefore} days");

    if ($today < $triggerDate || $today >= $nextMonthStart) {
        continue;
    }

    $householdsInWindow++;
    $householdId = (int) $household['id'];
    $singleUse = (bool) $household['budget_reminder_link_single_use'];
    $expiryDays = (int) $household['budget_reminder_link_expiry_days'];

    foreach ($householdRepo->listMembers($householdId) as $member) {
        if (!in_array($member['role'], ['owner', 'administrator'], true)) {
            continue;
        }

        $userId = (int) $member['id'];

        if ($linkRepo->existsForHouseholdUserMonth($householdId, $userId, $nextMonthKey)) {
            $skippedAlreadyIssued++;
            continue;
        }

        $link = $linkService->issue($householdId, $userId, $nextMonthKey, $singleUse, $expiryDays);

        // Email sending is stubbed for now (see CLAUDE.md) — the review
        // link is logged instead of sent, until real SMTP is wired up.
        Logger::info('Budget review reminder link generated (email stubbed)', [
            'household_id' => $householdId,
            'user_id' => $userId,
            'email' => $member['email'],
            'period_month' => $nextMonthKey,
            'review_url' => $link['url'],
            'expires_at' => $link['expiresAt'],
            'single_use' => $singleUse,
        ]);

        $auditLog->log(
            null,
            $householdId,
            'budget_review_link.issued',
            'budget_review_link',
            null,
            null,
            ['user_id' => $userId, 'period_month' => $nextMonthKey, 'expires_at' => $link['expiresAt']]
        );

        $issued++;
    }
}

echo "Checked households with reminders enabled — {$householdsInWindow} in their trigger window today.\n";
echo "Issued {$issued} new review link(s) for {$nextMonthKey}.\n";
if ($skippedAlreadyIssued > 0) {
    echo "Skipped {$skippedAlreadyIssued} recipient(s) who already have a link for {$nextMonthKey}.\n";
}
