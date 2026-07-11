<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\HouseholdController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\TransactionController;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\ErrorHandler;
use App\Support\View;
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

$accountController = new AccountController();
$router->get('/accounts', fn (): mixed => $accountController->index());
$router->get('/accounts/create', fn (): mixed => $accountController->showCreateForm());
$router->post('/accounts', fn (Request $r): mixed => $accountController->store($r));
$router->get('/accounts/{id}/edit', fn (Request $r): mixed => $accountController->showEditForm($r));
$router->post('/accounts/{id}', fn (Request $r): mixed => $accountController->update($r));
$router->post('/accounts/{id}/balance', fn (Request $r): mixed => $accountController->updateBalance($r));
$router->post('/accounts/{id}/archive', fn (Request $r): mixed => $accountController->archive($r));
$router->post('/accounts/{id}/restore', fn (Request $r): mixed => $accountController->restore($r));

$transactionController = new TransactionController();
$router->get('/transactions', fn (Request $r): mixed => $transactionController->index($r));
$router->get('/transactions/create', fn (): mixed => $transactionController->showCreateForm());
$router->post('/transactions', fn (Request $r): mixed => $transactionController->store($r));
$router->get('/transactions/{id}/edit', fn (Request $r): mixed => $transactionController->showEditForm($r));
$router->post('/transactions/{id}', fn (Request $r): mixed => $transactionController->update($r));
$router->post('/transactions/{id}/delete', fn (Request $r): mixed => $transactionController->destroy($r));

$categoryController = new CategoryController();
$router->get('/settings/categories', fn (): mixed => $categoryController->index());
$router->post('/settings/categories', fn (Request $r): mixed => $categoryController->store($r));
$router->post('/settings/categories/{id}', fn (Request $r): mixed => $categoryController->update($r));
$router->post('/settings/categories/{id}/archive', fn (Request $r): mixed => $categoryController->archive($r));
$router->post('/settings/categories/{id}/restore', fn (Request $r): mixed => $categoryController->restore($r));

$dashboardController = new DashboardController();
$router->post('/dashboard/layout', fn (Request $r): mixed => $dashboardController->saveLayout($r));
$router->post('/dashboard/widgets', fn (Request $r): mixed => $dashboardController->saveVisibility($r));

$router->get('/', function () use ($appConfig, $dashboardController): void {
    if (!empty($_SESSION['user_id'])) {
        $dashboardController->index();
        return;
    }

    $name = htmlspecialchars($appConfig['name'], ENT_QUOTES);
    $appCss = View::asset('/assets/css/app.css');

    Response::html(<<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$name}</title>
            <link rel="stylesheet" href="{$appCss}">
        </head>
        <body class="auth-shell">
            <div class="text-center">
                <h1 class="text-3xl font-semibold tracking-tight text-stone-900 dark:text-white mb-2">{$name}</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mb-6">A self-hosted household finance dashboard.</p>
                <a href="/login" class="btn-primary">Log in</a>
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
