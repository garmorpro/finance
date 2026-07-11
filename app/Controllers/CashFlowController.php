<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Services\ReportingService;
use App\Support\Csrf;
use App\Support\View;

final class CashFlowController
{
    private const MONTH_OPTIONS = [3, 6, 12, 24];

    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $months = $this->resolveMonths($request->query('months'));

        $series = (new ReportingService())->cashFlow($householdId, $months);

        $totalIncome = '0.00';
        $totalExpenses = '0.00';
        foreach ($series as $row) {
            $totalIncome = bcadd($totalIncome, $row['income'], 2);
            $totalExpenses = bcadd($totalExpenses, $row['expenses'], 2);
        }

        Response::html(View::render('cash-flow/index', [
            'series' => $series,
            'months' => $months,
            'monthOptions' => self::MONTH_OPTIONS,
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netCashFlow' => bcsub($totalIncome, $totalExpenses, 2),
            'csrfToken' => Csrf::token(),
        ]));
    }

    private function resolveMonths(string $value): int
    {
        $months = (int) $value;

        return in_array($months, self::MONTH_OPTIONS, true) ? $months : 6;
    }
}
