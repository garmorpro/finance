<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\PasswordPolicy;

final class ProfileController
{
    public function show(): void
    {
        AuthMiddleware::requireAuth();

        $user = (new UserRepository())->findById((int) AuthMiddleware::userId());

        Response::html(View::render('profile/edit', [
            'user' => $user,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function updateProfile(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $name = trim($request->post('name'));
        $email = trim(mb_strtolower($request->post('email')));

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /profile');
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

        (new AuditLogRepository())->log(
            $userId,
            AuthMiddleware::householdId(),
            'profile.updated',
            'user',
            $userId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Profile updated.';
        header('Location: /profile');
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
            header('Location: /profile');
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
        header('Location: /profile');
    }
}
