<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Support\Csrf;
use App\Support\RateLimiter;
use App\Support\Totp;
use App\Support\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        Response::html(View::render('auth/login', [
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function login(Request $request): void
    {
        $email = trim(mb_strtolower($request->post('email')));
        $password = $request->post('password');
        $ip = $request->ip();

        $userRepo = new UserRepository();
        $householdRepo = new HouseholdRepository();
        $auditLog = new AuditLogRepository();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $this->failLogin('Your session expired. Please try again.');
            return;
        }

        if (RateLimiter::tooManyAttempts($email, $ip)) {
            $auditLog->log(null, null, 'login.rate_limited', 'user', null, $ip, ['email' => $email]);
            $this->failLogin('Too many failed attempts. Please wait 15 minutes and try again.');
            return;
        }

        $user = $userRepo->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash']) || (int) $user['is_active'] !== 1) {
            RateLimiter::recordAttempt($email, $ip, false);
            $auditLog->log($user['id'] ?? null, null, 'login.failed', 'user', $user['id'] ?? null, $ip, ['email' => $email]);
            $this->failLogin('Invalid email or password.');
            return;
        }

        RateLimiter::recordAttempt($email, $ip, true);

        // Password is correct, but two-factor isn't satisfied yet — hold
        // off on establishing a real session. A pending, unauthenticated
        // marker only (regenerated session ID, no user_id) until the code
        // step succeeds.
        if ($user['two_factor_enabled_at'] !== null) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_email'] = $email;
            header('Location: /login/verify');
            return;
        }

        $userRepo->updateLastLogin((int) $user['id']);
        $this->completeLogin($user, $request, $householdRepo, $auditLog);
    }

    public function showTwoFactorChallenge(): void
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            header('Location: /login');
            return;
        }

        Response::html(View::render('auth/two-factor', [
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_error']);
    }

    public function verifyTwoFactor(Request $request): void
    {
        $userId = $_SESSION['pending_2fa_user_id'] ?? null;
        $email = $_SESSION['pending_2fa_email'] ?? '';
        $ip = $request->ip();

        if ($userId === null) {
            header('Location: /login');
            return;
        }

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /login/verify');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if (RateLimiter::tooManyAttempts($email, $ip)) {
            (new AuditLogRepository())->log((int) $userId, null, 'login.rate_limited', 'user', (int) $userId, $ip);
            $redirectBack('Too many failed attempts. Please wait 15 minutes and try again.');
            return;
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById((int) $userId);

        if ($user === null || $user['two_factor_enabled_at'] === null) {
            // Shouldn't happen outside of the account being changed
            // mid-flow (e.g. 2FA disabled in another tab) — fail safe
            // back to a completely fresh login rather than half-trusting
            // a pending session that no longer makes sense.
            $_SESSION = [];
            session_destroy();
            header('Location: /login');
            return;
        }

        $code = trim($request->post('code'));
        // Recovery codes are formatted with a dash (XXXX-XXXX); TOTP
        // codes are always 6 plain digits — enough to tell them apart
        // without a separate form field.
        $isRecoveryCode = str_contains($code, '-');

        $verified = $isRecoveryCode
            ? $userRepo->consumeRecoveryCode((int) $user['id'], $code)
            : Totp::verify($user['two_factor_secret'], $code);

        if (!$verified) {
            RateLimiter::recordAttempt($email, $ip, false);
            (new AuditLogRepository())->log((int) $user['id'], null, 'login.2fa_failed', 'user', (int) $user['id'], $ip);
            $redirectBack('Invalid code. Please try again.');
            return;
        }

        RateLimiter::recordAttempt($email, $ip, true);
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email']);

        if ($isRecoveryCode) {
            $_SESSION['_flash_notice'] = 'Signed in with a recovery code. Consider regenerating your recovery codes in Settings → Security.';
        }

        $userRepo->updateLastLogin((int) $user['id']);
        $this->completeLogin($user, $request, new HouseholdRepository(), new AuditLogRepository());
    }

    private function completeLogin(array $user, Request $request, HouseholdRepository $householdRepo, AuditLogRepository $auditLog): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        AuthMiddleware::setUserIdentity($user['name'], $user['email']);

        $membership = $householdRepo->findMembership((int) $user['id']);
        if ($membership !== null) {
            $_SESSION['household_id'] = (int) $membership['household_id'];
            $_SESSION['role'] = $membership['role'];
        }

        $sessionId = session_id();
        if ($sessionId !== false && $sessionId !== '') {
            (new UserSessionRepository())->recordSession(
                (int) $user['id'],
                $sessionId,
                $request->ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        }

        $auditLog->log(
            (int) $user['id'],
            $_SESSION['household_id'] ?? null,
            'login.success',
            'user',
            (int) $user['id'],
            $request->ip()
        );

        header('Location: /');
    }

    public function logout(Request $request): void
    {
        if (!Csrf::verify($request->post('csrf_token'))) {
            header('Location: /');
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $householdId = $_SESSION['household_id'] ?? null;
        $sessionId = session_id();

        if ($userId !== null) {
            (new AuditLogRepository())->log((int) $userId, $householdId, 'logout', 'user', (int) $userId, $request->ip());
        }

        if ($sessionId !== false && $sessionId !== '') {
            (new UserSessionRepository())->revokeBySessionId($sessionId);
        }

        $_SESSION = [];
        session_destroy();

        header('Location: /login');
    }

    private function failLogin(string $message): void
    {
        $_SESSION['_flash_error'] = $message;
        header('Location: /login');
    }
}
