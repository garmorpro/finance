<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\View;

final class DashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $user = (new UserRepository())->findById((int) AuthMiddleware::userId());

        $householdId = AuthMiddleware::householdId();
        $household = $householdId !== null ? (new HouseholdRepository())->findById($householdId) : null;

        Response::html(View::render('dashboard/index', [
            'user' => $user,
            'household' => $household,
            'role' => $_SESSION['role'] ?? null,
            'csrfToken' => Csrf::token(),
        ]));
    }
}
