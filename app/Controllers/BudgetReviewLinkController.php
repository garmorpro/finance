<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Services\BudgetReviewLinkService;
use App\Support\View;

/**
 * Landing point for the budget planning reminder email's magic link
 * (see bin/send-budget-reminders.php and Settings > Household). A link
 * only ever unlocks the one household/user/month it was issued for —
 * never a general sign-in — enforced here and re-checked on every
 * subsequent request by BudgetController::resolveReviewAccess().
 */
final class BudgetReviewLinkController
{
    private const ELIGIBLE_ROLES = ['owner', 'administrator'];

    public function open(Request $request): void
    {
        $token = $request->param('token') ?? '';
        $service = new BudgetReviewLinkService();

        $link = $token !== '' ? $service->peek($token) : null;

        if ($link === null) {
            Response::html(View::render('budgets/review-link-invalid'), 400);
            return;
        }

        // Re-verified now, not just trusted from when the link was
        // issued — a household can change roles at any time, and a link
        // sent while someone was an Administrator shouldn't still work
        // after they've been demoted or removed.
        $membership = (new HouseholdRepository())->findMembership($link['userId']);
        $stillEligible = $membership !== null
            && (int) $membership['household_id'] === $link['householdId']
            && in_array($membership['role'], self::ELIGIBLE_ROLES, true);

        if (!$stillEligible) {
            Response::html(View::render('budgets/review-link-invalid', [
                'message' => "This link's recipient no longer has permission to manage this household's budget.",
            ]), 400);
            return;
        }

        // Someone already signed in on this device as someone other than
        // the link's recipient — refuse rather than silently swapping
        // their real session for the link's identity, and leave the
        // link unspent since this wasn't a legitimate attempt to use it.
        if (AuthMiddleware::check() && AuthMiddleware::userId() !== $link['userId']) {
            Response::html(View::render('budgets/review-link-invalid', [
                'message' => 'You\'re signed in as ' . AuthMiddleware::userName() . '. This link is for a different household member.',
                'showLogout' => true,
            ]), 409);
            return;
        }

        if (!$service->consume($link['id'])) {
            // Lost a race with another request for the same single-use
            // link between peek() and here.
            Response::html(View::render('budgets/review-link-invalid'), 400);
            return;
        }

        (new AuditLogRepository())->log(
            $link['userId'],
            $link['householdId'],
            'budget_review_link.opened',
            'budget_review_link',
            $link['id'],
            $request->ip(),
            ['period_month' => $link['periodMonth']]
        );

        // Already fully signed in as the link's own recipient — keep
        // their real session and real permissions, the link just deep-
        // links them to the right month.
        if (AuthMiddleware::check()) {
            header('Location: /budgets/review?month=' . substr($link['periodMonth'], 0, 7));
            return;
        }

        // Not signed in at all — the link is the only thing establishing
        // who they are, so it only ever grants the narrow scoped access
        // BudgetController::resolveReviewAccess() checks for, never a
        // real session (no user_id/household_id/role are set here).
        session_regenerate_id(true);
        $_SESSION['review_link_household_id'] = $link['householdId'];
        $_SESSION['review_link_user_id'] = $link['userId'];
        $_SESSION['review_link_period_month'] = $link['periodMonth'];

        header('Location: /budgets/review');
    }
}
