<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HouseholdController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\ErrorHandler;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$appConfig = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

ErrorHandler::register($appConfig['debug']);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => str_starts_with($appConfig['url'], 'https://'),
    'samesite' => 'Lax',
]);
session_start();

$router = new Router();

$authController = new AuthController();
$router->get('/login', fn (): mixed => $authController->showLogin());
$router->post('/login', fn (Request $r): mixed => $authController->login($r));
$router->post('/logout', fn (Request $r): mixed => $authController->logout($r));

$passwordResetController = new PasswordResetController();
$router->get('/forgot-password', fn (): mixed => $passwordResetController->showForgotForm());
$router->post('/forgot-password', fn (Request $r): mixed => $passwordResetController->sendReset($r));
$router->get('/reset-password', fn (Request $r): mixed => $passwordResetController->showResetForm($r));
$router->post('/reset-password', fn (Request $r): mixed => $passwordResetController->resetPassword($r));

$householdController = new HouseholdController();
$router->get('/household', fn (): mixed => $householdController->showMembers());
$router->post('/household/invite', fn (Request $r): mixed => $householdController->sendInvite($r));
$router->get('/accept-invite', fn (): mixed => $householdController->showAcceptForm());
$router->post('/accept-invite', fn (Request $r): mixed => $householdController->acceptInvite($r));

$profileController = new ProfileController();
$router->get('/profile', fn (): mixed => $profileController->show());
$router->post('/profile', fn (Request $r): mixed => $profileController->updateProfile($r));
$router->post('/profile/password', fn (Request $r): mixed => $profileController->updatePassword($r));

$dashboardController = new DashboardController();

$router->get('/', function () use ($appConfig, $dashboardController): void {
    if (!empty($_SESSION['user_id'])) {
        $dashboardController->index();
        return;
    }

    $name = htmlspecialchars($appConfig['name'], ENT_QUOTES);

    Response::html(<<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>{$name}</title>
        </head>
        <body style="font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #0f172a; color: #f1f5f9;">
            <div style="text-align: center;">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">{$name}</h1>
                <p style="color: #94a3b8; margin-bottom: 1.5rem;">Under construction — Phase 1 foundation is live.</p>
                <a href="/login" style="color: #3b82f6;">Log in</a>
            </div>
        </body>
        </html>
        HTML
    );
});

$router->get('/health', function (): void {
    $status = 'ok';
    $dbStatus = 'ok';

    try {
        Connection::get()->query('SELECT 1');
    } catch (\Throwable $e) {
        $status = 'degraded';
        $dbStatus = 'unreachable';
    }

    Response::json([
        'status' => $status,
        'database' => $dbStatus,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);
});

$router->dispatch(new Request());
