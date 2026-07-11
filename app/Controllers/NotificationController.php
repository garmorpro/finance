<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Support\Csrf;
use App\Support\View;

final class NotificationController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();
        $service = new NotificationService();

        Response::html(View::render('notifications/index', [
            'notifications' => $service->activeNotifications($householdId, $userId),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function settings(): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $service = new NotificationService();

        Response::html(View::render('settings/notifications', [
            'types' => NotificationService::TYPES,
            'preferences' => $service->preferencesForUser($userId),
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_notice'], $_SESSION['_flash_error']);
    }

    public function updatePreferences(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/notifications');
            return;
        }

        $userId = (int) AuthMiddleware::userId();
        $enabledTypes = $request->postArray('enabled_types');
        $service = new NotificationService();

        foreach (array_keys(NotificationService::TYPES) as $type) {
            $service->setPreference($userId, $type, in_array($type, $enabledTypes, true));
        }

        $_SESSION['_flash_notice'] = 'Notification preferences updated.';
        header('Location: /settings/notifications');
    }
}
