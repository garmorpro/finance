<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Support\Csrf;
use App\Support\Totp;
use App\Support\UserAgent;
use App\Support\View;
use App\Validation\PasswordPolicy;

final class ProfileController
{
    public function show(): void
    {
        AuthMiddleware::requireAuth();

        $user = (new UserRepository())->findById((int) AuthMiddleware::userId());

        Response::html(View::render('settings/profile', [
            'user' => $user,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function showSecurity(): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $user = (new UserRepository())->findById($userId);
        $currentSessionId = session_id();

        $sessions = array_map(function (array $session) use ($currentSessionId): array {
            return [
                'id' => (int) $session['id'],
                'label' => UserAgent::describe($session['user_agent']),
                'ip_address' => $session['ip_address'],
                'last_active_at' => $session['last_active_at'],
                'is_current' => $currentSessionId !== false && $session['session_id'] === $currentSessionId,
            ];
        }, (new UserSessionRepository())->listActiveForUser($userId));

        // Current session first, then most-recently-active.
        usort($sessions, fn (array $a, array $b): int => $b['is_current'] <=> $a['is_current']);

        Response::html(View::render('settings/security', [
            'twoFactorEnabled' => $user !== null && $user['two_factor_enabled_at'] !== null,
            'sessions' => $sessions,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function revokeSession(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $sessionRowId = (int) $request->param('id');

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/security');
            return;
        }

        (new UserSessionRepository())->revoke($sessionRowId, $userId);

        (new AuditLogRepository())->log($userId, AuthMiddleware::householdId(), 'session.revoked', 'user_session', $sessionRowId, $request->ip());

        $_SESSION['_flash_notice'] = 'That session was logged out.';
        header('Location: /settings/security');
    }

    public function revokeAllOtherSessions(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $currentSessionId = session_id();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/security');
            return;
        }

        $revoked = $currentSessionId !== false
            ? (new UserSessionRepository())->revokeAllExcept($userId, $currentSessionId)
            : 0;

        (new AuditLogRepository())->log($userId, AuthMiddleware::householdId(), 'session.revoked_all_others', 'user', $userId, $request->ip(), ['count' => $revoked]);

        $_SESSION['_flash_notice'] = $revoked > 0
            ? "Logged out {$revoked} other session" . ($revoked === 1 ? '' : 's') . '.'
            : 'No other active sessions to log out.';
        header('Location: /settings/security');
    }

    public function showTwoFactorSetup(): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $user = (new UserRepository())->findById($userId);

        if ($user !== null && $user['two_factor_enabled_at'] !== null) {
            header('Location: /settings/security');
            return;
        }

        // A fresh secret each time this page loads avoids ever committing
        // to one the user never actually confirmed — nothing is written
        // to the database until enableTwoFactor() verifies a real code
        // against it.
        $secret = Totp::generateSecret();
        $_SESSION['pending_totp_secret'] = $secret;

        Response::html(View::render('settings/two-factor-setup', [
            'secretFormatted' => trim((string) chunk_split($secret, 4, ' ')),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_error']);
    }

    public function enableTwoFactor(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /settings/security/2fa/setup');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $secret = $_SESSION['pending_totp_secret'] ?? null;
        if (!is_string($secret)) {
            $redirectBack('Your setup session expired. Please start again.');
            return;
        }

        $code = trim($request->post('code'));
        if (!Totp::verify($secret, $code)) {
            $redirectBack("That code doesn't match. Please check your authenticator app and try again.");
            return;
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $hashes = array_map(static fn (string $c): string => password_hash($c, PASSWORD_DEFAULT), $recoveryCodes);

        (new UserRepository())->enableTwoFactor($userId, $secret, $hashes);
        unset($_SESSION['pending_totp_secret']);

        (new AuditLogRepository())->log($userId, AuthMiddleware::householdId(), '2fa.enabled', 'user', $userId, $request->ip());

        // Recovery codes are stored hashed — this session flash is the
        // only chance the user gets to see the plaintext values, so the
        // next page renders them once and they're gone.
        $_SESSION['_flash_recovery_codes'] = $recoveryCodes;
        header('Location: /settings/security/2fa/recovery-codes');
    }

    public function showRecoveryCodes(): void
    {
        AuthMiddleware::requireAuth();

        $codes = $_SESSION['_flash_recovery_codes'] ?? null;
        if (!is_array($codes)) {
            header('Location: /settings/security');
            return;
        }

        unset($_SESSION['_flash_recovery_codes']);

        Response::html(View::render('settings/two-factor-recovery-codes', [
            'codes' => $codes,
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function disableTwoFactor(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /settings/security');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById($userId);

        // Critical account action — require password confirmation, per
        // CLAUDE.md, so a hijacked-but-still-logged-in session (e.g. an
        // unlocked laptop) can't silently turn off 2FA.
        if ($user === null || !password_verify($request->post('current_password'), $user['password_hash'])) {
            $redirectBack('Current password is incorrect.');
            return;
        }

        $userRepo->disableTwoFactor($userId);

        (new AuditLogRepository())->log($userId, AuthMiddleware::householdId(), '2fa.disabled', 'user', $userId, $request->ip());

        $_SESSION['_flash_notice'] = 'Two-factor authentication turned off.';
        header('Location: /settings/security');
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        }

        return $codes;
    }

    public function updateProfile(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $name = trim($request->post('name'));
        $email = trim(mb_strtolower($request->post('email')));

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /settings/profile');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if ($name === '') {
            $redirectBack('Name is required.');
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $redirectBack('Please enter a valid email address.');
            return;
        }

        $userRepo = new UserRepository();
        $existing = $userRepo->findByEmail($email);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            $redirectBack('That email is already in use.');
            return;
        }

        $userRepo->updateProfile($userId, $name, $email);
        AuthMiddleware::setUserIdentity($name, $email);

        (new AuditLogRepository())->log(
            $userId,
            AuthMiddleware::householdId(),
            'profile.updated',
            'user',
            $userId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Profile updated.';
        header('Location: /settings/profile');
    }

    public function updatePassword(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $currentPassword = $request->post('current_password');
        $newPassword = $request->post('new_password');
        $confirm = $request->post('new_password_confirmation');

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /settings/security');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById($userId);

        if ($user === null || !password_verify($currentPassword, $user['password_hash'])) {
            $redirectBack('Current password is incorrect.');
            return;
        }

        if (!PasswordPolicy::isValid($newPassword)) {
            $redirectBack(PasswordPolicy::message());
            return;
        }

        if (!hash_equals($newPassword, $confirm)) {
            $redirectBack('New passwords do not match.');
            return;
        }

        $userRepo->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));

        (new AuditLogRepository())->log(
            $userId,
            AuthMiddleware::householdId(),
            'password.changed',
            'user',
            $userId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Password updated.';
        header('Location: /settings/security');
    }
}
