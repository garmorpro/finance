<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Support\Csrf;
use App\Support\View;

/**
 * Settings > Audit Log — a household's own security/activity trail
 * (logins, transaction edits, settings changes, Quick Add key
 * activity, ...), the in-app counterpart to bin/audit-access.php's
 * whole-server CLI report. Owner-only (see docs/security.md's
 * "Audit log" section for why), so nothing here needs a "canManage"-
 * style reduced view the way Settings > Household does — you either
 * see the household's security trail or you don't.
 */
final class AuditLogController
{
    public function index(Request $request): void
    {
        AuthMiddleware::requireRole(['owner']);

        $householdId = (int) AuthMiddleware::householdId();

        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $page = max(1, (int) $request->query('page', '1'));

        $result = (new AuditLogRepository())->listForHousehold($householdId, $filters, $page);

        Response::html(View::render('settings/audit-log', [
            'entries' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'filters' => $filters,
            'csrfToken' => Csrf::token(),
        ]));
    }
}
