<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Support\Csrf;
use App\Support\View;

/**
 * Honest placeholder pages for a future business-tracking feature — no
 * business account type, transaction tagging, or P&L data model exists
 * yet. That's a genuinely new domain needing its own entity-relationship
 * design pass, not something to scaffold blindly here. These pages exist
 * so the sidebar's "Business" section links somewhere real instead of a
 * dead link, and show an honest empty state rather than fabricated data.
 */
final class BusinessController
{
    public function overview(): void
    {
        AuthMiddleware::requireAuth();

        Response::html(View::render('business/overview', [
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function transactions(): void
    {
        AuthMiddleware::requireAuth();

        Response::html(View::render('business/transactions', [
            'csrfToken' => Csrf::token(),
        ]));
    }
}
