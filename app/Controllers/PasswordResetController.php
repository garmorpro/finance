<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\Logger;
use App\Support\View;
use App\Validation\PasswordPolicy;

final class PasswordResetController
{
    public function showForgotForm(): void
    {
        Response::html(View::render('auth/forgot-password', [
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
    }

    public function sendReset(Request $request): void
    {
        $email = trim(mb_strtolower($request->post('email')));

        if (!Csrf::verify($request->post('csrf_token'))) {
            header('Location: /forgot-password');
            return;
        }

        $user = (new UserRepository())->findByEmail($email);

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $resetRepo = new PasswordResetRepository();
            $resetRepo->invalidateAllForUser((int) $user['id']);
            $resetRepo->create(
                (int) $user['id'],
                $tokenHash,
                gmdate('Y-m-d H:i:s', time() + 3600)
            );

            $resetUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/reset-password?token=' . $token;

            // Email sending is stubbed for now (see CLAUDE.md) — the reset
            // link is logged instead of sent, until real SMTP is wired up.
            Logger::info('Password reset link generated (email stubbed)', [
                'user_id' => $user['id'],
                'email' => $email,
                'reset_url' => $resetUrl,
            ]);

            (new AuditLogRepository())->log(
                (int) $user['id'],
                null,
                'password_reset.requested',
                'user',
                (int) $user['id'],
                $request->ip()
            );
        }

        // Always show the same message, whether or not the email exists,
        // so this endpoint can't be used to enumerate registered accounts.
        $_SESSION['_flash_notice'] = 'If that email is registered, a password reset link has been generated.';
        header('Location: /forgot-password');
    }

    public function showResetForm(Request $request): void
    {
        $token = $_GET['token'] ?? '';

        if (!is_string($token) || $token === '' || (new PasswordResetRepository())->findValidByTokenHash(hash('sha256', $token)) === null) {
            Response::html(View::render('auth/reset-password-invalid'), 400);
            return;
        }

        Response::html(View::render('auth/reset-password', [
            'token' => $token,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_error']);
    }

    public function resetPassword(Request $request): void
    {
        $token = $request->post('token');
        $password = $request->post('password');
        $confirm = $request->post('password_confirmation');

        $redirectBack = function (string $message) use ($token): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /reset-password?token=' . urlencode($token));
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $resetRepo = new PasswordResetRepository();
        $tokenRow = $resetRepo->findValidByTokenHash(hash('sha256', $token));

        if ($tokenRow === null) {
            Response::html(View::render('auth/reset-password-invalid'), 400);
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
        $userRepo->updatePassword((int) $tokenRow['user_id'], password_hash($password, PASSWORD_DEFAULT));

        $resetRepo->markUsed((int) $tokenRow['id']);
        $resetRepo->invalidateAllForUser((int) $tokenRow['user_id']);

        (new AuditLogRepository())->log(
            (int) $tokenRow['user_id'],
            null,
            'password_reset.completed',
            'user',
            (int) $tokenRow['user_id'],
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Your password has been reset. Please log in.';
        header('Location: /login');
    }
}
