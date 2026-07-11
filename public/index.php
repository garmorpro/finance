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
