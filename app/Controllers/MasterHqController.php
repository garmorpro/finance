<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\HouseholdRepository;
use App\Services\MasterHqSummaryService;
use App\Support\MasterHqAuth;

/**
 * Read-only, server-to-server API for Master HQ — see docs/api.md's
 * "Master HQ read-only reporting API" section for the full contract.
 * Deliberately outside the browser session/CSRF model every other
 * controller uses: this is called by another server, not a signed-in
 * user's browser, so it authenticates with a static bearer token
 * instead (App\Support\MasterHqAuth) and never touches $_SESSION.
 */
final class MasterHqController
{
    public function summary(Request $request): void
    {
        if (!MasterHqAuth::isAuthorized(MasterHqAuth::bearerToken($request))) {
            // Deliberately the same generic message whether the token was
            // missing, malformed, or simply wrong — never reveal which,
            // that just helps someone probe toward a valid credential.
            Response::json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $householdId = (int) ($_ENV['MASTER_HQ_HOUSEHOLD_ID'] ?? 0);

        if ($householdId <= 0 || (new HouseholdRepository())->findById($householdId) === null) {
            // A configuration problem (missing/invalid
            // MASTER_HQ_HOUSEHOLD_ID), not something the caller did
            // wrong — logged for whoever runs this server to fix, not
            // exposed to the caller beyond a generic 500.
            error_log('MasterHqController: MASTER_HQ_HOUSEHOLD_ID is not set to a real household id.');
            Response::json(['error' => 'Server misconfiguration.'], 500);
            return;
        }

        try {
            $summary = (new MasterHqSummaryService())->buildSummary($householdId);
        } catch (\Throwable $e) {
            error_log('MasterHqController::summary failed: ' . $e->getMessage());
            Response::json(['error' => 'Internal error.'], 500);
            return;
        }

        Response::json($summary);
    }
}
