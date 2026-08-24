<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\Logger;
use App\Support\View;
use App\Validation\PasswordPolicy;

final class HouseholdController
{
    private const MANAGE_ROLES = ['owner', 'administrator'];
    private const INVITABLE_ROLES = ['administrator', 'member', 'viewer'];

    public function showMembers(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = AuthMiddleware::householdId();

        if ($householdId === null) {
            Response::html('No household found for this account.', 404);
            return;
        }

        $householdRepo = new HouseholdRepository();

        Response::html(View::render('settings/household', [
            'household' => $householdRepo->findById($householdId),
            'members' => $householdRepo->listMembers($householdId),
            'pendingInvitations' => (new InvitationRepository())->listPendingForHousehold($householdId),
            'canManage' => in_array(AuthMiddleware::role(), self::MANAGE_ROLES, true),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function sendInvite(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = AuthMiddleware::householdId();
        $email = trim(mb_strtolower($request->post('email')));
        $role = $request->post('role');

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /settings/household');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if ($householdId === null) {
            $redirectBack('No household found for this account.');
            return;
        }

        if (!in_array($role, self::INVITABLE_ROLES, true)) {
            $redirectBack('Please choose a valid role.');
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $redirectBack('Please enter a valid email address.');
            return;
        }

        $userRepo = new UserRepository();
        $invitationRepo = new InvitationRepository();

        if ($userRepo->findByEmail($email) !== null) {
            $redirectBack('That person already has an account.');
            return;
        }

        if ($invitationRepo->findPendingByHouseholdAndEmail($householdId, $email) !== null) {
            $redirectBack('There is already a pending invitation for that email.');
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $invitationRepo->create(
            $householdId,
            (int) AuthMiddleware::userId(),
            $email,
            $role,
            $tokenHash,
            gmdate('Y-m-d H:i:s', time() + 7 * 24 * 3600)
        );

        $inviteUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/accept-invite?token=' . $token;

        // Email sending is stubbed for now (see CLAUDE.md) — the invite
        // link is logged instead of sent, until real SMTP is wired up.
        Logger::info('Household invitation created (email stubbed)', [
            'household_id' => $householdId,
            'email' => $email,
            'role' => $role,
            'invite_url' => $inviteUrl,
        ]);

        (new AuditLogRepository())->log(
            AuthMiddleware::userId(),
            $householdId,
            'invitation.sent',
            'household_invitation',
            null,
            $request->ip(),
            ['email' => $email, 'role' => $role]
        );

        $_SESSION['_flash_notice'] = "Invitation created for {$email}.";
        header('Location: /settings/household');
    }

    /**
     * The household-wide policy behind the budget planning reminder
     * email (see bin/send-budget-reminders.php) — whether it's on, how
     * many days before the month starts it goes out, and how the magic
     * link it carries behaves. This is household-wide config, not a
     * per-user preference like Settings > Notifications, so it lives
     * here alongside membership management rather than there.
     */
    public function updateBudgetReminderSettings(Request $request): void
    {
        AuthMiddleware::requireRole(self::MANAGE_ROLES);

        $householdId = AuthMiddleware::householdId();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/household');
            return;
        }

        if ($householdId === null) {
            $_SESSION['_flash_error'] = 'No household found for this account.';
            header('Location: /settings/household');
            return;
        }

        $enabled = $request->post('enabled') === '1';
        $daysBefore = (int) $request->post('days_before');
        $linkSingleUse = $request->post('link_single_use') !== '0';
        $linkExpiryDays = (int) $request->post('link_expiry_days');

        if ($daysBefore < 1 || $daysBefore > 28) {
            $daysBefore = 5;
        }
        if ($linkExpiryDays < 1 || $linkExpiryDays > 30) {
            $linkExpiryDays = 7;
        }

        (new HouseholdRepository())->updateBudgetReminderSettings($householdId, $enabled, $daysBefore, $linkSingleUse, $linkExpiryDays);

        (new AuditLogRepository())->log(
            AuthMiddleware::userId(),
            $householdId,
            'household.budget_reminder_settings_updated',
            'household',
            $householdId,
            $request->ip(),
            ['enabled' => $enabled, 'days_before' => $daysBefore, 'link_single_use' => $linkSingleUse, 'link_expiry_days' => $linkExpiryDays]
        );

        $_SESSION['_flash_notice'] = 'Budget planning reminder settings updated.';
        header('Location: /settings/household');
    }

    public function showAcceptForm(): void
    {
        $token = $_GET['token'] ?? '';

        $invitation = is_string($token) && $token !== ''
            ? (new InvitationRepository())->findValidByTokenHash(hash('sha256', $token))
            : null;

        if ($invitation === null) {
            Response::html(View::render('household/invite-invalid'), 400);
            return;
        }

        Response::html(View::render('household/accept-invite', [
            'token' => $token,
            'email' => $invitation['email'],
            'role' => $invitation['role'],
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_error']);
    }

    public function acceptInvite(Request $request): void
    {
        $token = $request->post('token');
        $name = trim($request->post('name'));
        $password = $request->post('password');
        $confirm = $request->post('password_confirmation');

        $redirectBack = function (string $message) use ($token): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /accept-invite?token=' . urlencode($token));
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $invitationRepo = new InvitationRepository();
        $invitation = $invitationRepo->findValidByTokenHash(hash('sha256', $token));

        if ($invitation === null) {
            Response::html(View::render('household/invite-invalid'), 400);
            return;
        }

        if ($name === '') {
            $redirectBack('Please enter your name.');
            return;
        }

        if (!PasswordPolicy::isValid($password)) {
            $redirectBack(PasswordPolicy::message());
            return;
        }

        if (!hash_equals($password, $confirm)) {
            $redirectBack('Passwords do not match.');
            return;
        }

        $userRepo = new UserRepository();

        if ($userRepo->findByEmail($invitation['email']) !== null) {
            $redirectBack('An account with this email already exists. Please log in instead.');
            return;
        }

        $userId = $userRepo->create($name, $invitation['email'], password_hash($password, PASSWORD_DEFAULT));

        $householdRepo = new HouseholdRepository();
        $householdRepo->addMember((int) $invitation['household_id'], $userId, $invitation['role']);
        $invitationRepo->markAccepted((int) $invitation['id']);

        (new AuditLogRepository())->log(
            $userId,
            (int) $invitation['household_id'],
            'invitation.accepted',
            'user',
            $userId,
            $request->ip()
        );

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['household_id'] = (int) $invitation['household_id'];
        $_SESSION['role'] = $invitation['role'];
        AuthMiddleware::setUserIdentity($name, $invitation['email']);

        header('Location: /');
    }
}
