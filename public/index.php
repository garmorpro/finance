<?php

declare(strict_types=1);

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

$router = new Router();

$router->get('/', function () use ($appConfig): void {
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
                <p style="color: #94a3b8;">Under construction — Phase 1 foundation is live.</p>
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
