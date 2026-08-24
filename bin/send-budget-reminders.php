<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\BudgetReviewLinkRepository;
use App\Repositories\HouseholdRepository;
use App\Services\BudgetReviewLinkService;
use App\Support\Logger;
use App\Support\Mailer;
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
 * Actually emails the link when MAIL_* is configured in .env
 * (App\Support\Mailer, SMTP via PHPMailer). Falls back to logging it
 * instead — same convention this app still uses for password resets and
 * household invitations — whenever mail isn't configured, or a send
 * attempt fails for any reason (bad credentials, host unreachable,
 * rejected by the receiving server). Either way the link itself is
 * always issued and always usable; only whether an email actually
 * reaches an inbox is best-effort.
 *
 *   php bin/send-budget-reminders.php
 */

/**
 * @return array{subject: string, html: string, text: string}
 */
function buildReminderEmail(string $recipientName, string $monthLabel, string $reviewUrl, int $expiryDays, bool $singleUse): array
{
    $firstName = trim(explode(' ', $recipientName, 2)[0]);
    $subject = 'Plan your ' . $monthLabel . ' budget';
    $expiryNote = 'This link is yours alone. It expires in ' . $expiryDays . ' day' . ($expiryDays === 1 ? '' : 's')
        . ($singleUse ? ', or as soon as you use it once' : '') . ' — whichever comes first.';

    $html = <<<HTML
        <div style="background:#f5f5f4;padding:32px 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
          <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e7e5e4;">
            <div style="padding:32px 28px 8px;">
              <div style="font-weight:700;font-size:16px;color:#1c1917;margin-bottom:28px;">MyCFO<span style="color:#e2694b;">+</span></div>
              <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;color:#1c1917;">Let's get {$monthLabel} planned.</h1>
              <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#57534e;">
                Hey {$firstName} — it's almost {$monthLabel}. Before it starts, take a couple of minutes
                to confirm what you're planning to earn and spend. Every category is pre-filled from
                recent months, so most of this is just a tap to confirm.
              </p>
              <div style="margin-bottom:24px;">
                <a href="{$reviewUrl}" style="display:inline-block;background:#e2694b;color:#ffffff;font-weight:600;font-size:15px;padding:13px 26px;border-radius:10px;text-decoration:none;">Review {$monthLabel}'s Budget &rarr;</a>
              </div>
              <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#a39c96;">
                Button not working? Paste this into your browser:<br>
                <span style="word-break:break-all;color:#78716c;">{$reviewUrl}</span><br>
                {$expiryNote}
              </p>
              <hr style="border:none;border-top:1px solid #e7e5e4;margin:0 0 20px;">
              <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#a39c96;">
                This email doesn't include any account balances or transaction data.
              </p>
              <p style="margin:0 0 24px;font-size:12px;line-height:1.6;color:#a39c96;">
                You're getting this because budget planning reminders are on for your household.
                Manage this in Settings &rsaquo; Household.
              </p>
            </div>
          </div>
        </div>
        HTML;

    $text = "Let's get {$monthLabel} planned.\n\n"
        . "Hey {$firstName} — it's almost {$monthLabel}. Before it starts, take a couple of minutes to "
        . "confirm what you're planning to earn and spend. Every category is pre-filled from recent months.\n\n"
        . "Review {$monthLabel}'s budget: {$reviewUrl}\n\n"
        . $expiryNote . "\n\n"
        . "This email doesn't include any account balances or transaction data. You're getting this "
        . "because budget planning reminders are on for your household — manage this in Settings > Household.";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$today = new DateTimeImmutable('today');
$nextMonthStart = $today->modify('first day of next month');
$nextMonthKey = $nextMonthStart->format('Y-m-d');
$monthLabel = $nextMonthStart->format('F Y');

$householdRepo = new HouseholdRepository();
$linkRepo = new BudgetReviewLinkRepository();
$linkService = new BudgetReviewLinkService();
$auditLog = new AuditLogRepository();

$issued = 0;
$emailed = 0;
$skippedAlreadyIssued = 0;
$householdsInWindow = 0;

if (!Mailer::isConfigured()) {
    echo "Note: MAIL_* isn't fully configured in .env — every link below will be logged, not emailed.\n\n";
}

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
        $issued++;

        $email = buildReminderEmail($member['name'], $monthLabel, $link['url'], $expiryDays, $singleUse);
        $sent = Mailer::send($member['email'], $member['name'], $email['subject'], $email['html'], $email['text']);

        if ($sent) {
            $emailed++;
            echo "Emailed {$member['email']} for {$monthLabel}.\n";
        } else {
            // Falls back to the same "logged instead of sent" convention
            // this app already uses for password resets and invitations.
            Logger::info('Budget review reminder link generated (email not sent — see Mailer log above)', [
                'household_id' => $householdId,
                'user_id' => $userId,
                'email' => $member['email'],
                'period_month' => $nextMonthKey,
                'review_url' => $link['url'],
                'expires_at' => $link['expiresAt'],
                'single_use' => $singleUse,
            ]);
            echo "Logged (not emailed) for {$member['email']} — {$link['url']}\n";
        }

        $auditLog->log(
            null,
            $householdId,
            'budget_review_link.issued',
            'budget_review_link',
            null,
            null,
            ['user_id' => $userId, 'period_month' => $nextMonthKey, 'expires_at' => $link['expiresAt'], 'emailed' => $sent]
        );
    }
}

echo "\nChecked households with reminders enabled — {$householdsInWindow} in their trigger window today.\n";
echo "Issued {$issued} new review link(s) for {$monthLabel} ({$emailed} emailed, " . ($issued - $emailed) . " logged only).\n";
if ($skippedAlreadyIssued > 0) {
    echo "Skipped {$skippedAlreadyIssued} recipient(s) who already have a link for {$nextMonthKey}.\n";
}
