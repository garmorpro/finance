<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\RateLimiter;
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
        ]));

        unset($_SESSION['_flash_error']);
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
        $userRepo->updateLastLogin((int) $user['id']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        $membership = $householdRepo->findMembership((int) $user['id']);
        if ($membership !== null) {
            $_SESSION['household_id'] = (int) $membership['household_id'];
            $_SESSION['role'] = $membership['role'];
        }

        $auditLog->log(
            (int) $user['id'],
            $_SESSION['household_id'] ?? null,
            'login.success',
            'user',
            (int) $user['id'],
            $ip
        );

        header('Location: /');
    }

    public function logout(Request $request): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $householdId = $_SESSION['household_id'] ?? null;

        if ($userId !== null) {
            (new AuditLogRepository())->log((int) $userId, $householdId, 'logout', 'user', (int) $userId, $request->ip());
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
