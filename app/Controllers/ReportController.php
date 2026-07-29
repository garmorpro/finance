<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\TagRepository;
use App\Services\ReportingService;
use App\Support\Csrf;
use App\Support\View;

final class ReportController
{
    private const GROUP_OPTIONS = ['category', 'merchant', 'account', 'month', 'member'];
    private const TYPE_OPTIONS = ['', 'income', 'expense', 'transfer'];

    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $filters = $this->readFilters($request);
        $groupBy = $this->resolveGroupBy($request->query('group_by'));
        $sort = $this->resolveSort($request->query('sort'));
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $report = (new ReportingService())->transactionsReport($householdId, $filters, $groupBy, $sort, $dir);

        Response::html(View::render('reports/index', [
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'groupBy' => $groupBy,
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $filters,
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId, true),
            'members' => (new HouseholdRepository())->listMembers($householdId),
            'tags' => (new TagRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function export(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $filters = $this->readFilters($request);
        $groupBy = $this->resolveGroupBy($request->query('group_by'));
        $sort = $this->resolveSort($request->query('sort'));
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $report = (new ReportingService())->transactionsReport($householdId, $filters, $groupBy, $sort, $dir);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $groupBy . '-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [ucfirst($groupBy), 'Income', 'Expenses', 'Transfers', 'Net', 'Transactions']);

        foreach ($report['rows'] as $row) {
            fputcsv($out, [$row['label'], $row['income'], $row['expense'], $row['transfer'], $row['net'], $row['count']]);
        }

        fputcsv($out, ['Total', $report['totals']['income'], $report['totals']['expense'], $report['totals']['transfer'], $report['totals']['net'], $report['totals']['count']]);

        fclose($out);
    }

    /**
     * @return array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, tag_ids: list<int>, type: string, user_id: int|null}
     */
    private function readFilters(Request $request): array
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($dateFrom === '' || \DateTime::createFromFormat('Y-m-d', $dateFrom) === false) {
            $dateFrom = date('Y-m-01', strtotime('-2 months'));
        }

        if ($dateTo === '' || \DateTime::createFromFormat('Y-m-d', $dateTo) === false) {
            $dateTo = gmdate('Y-m-d');
        }

        $type = $request->query('type');
        if (!in_array($type, self::TYPE_OPTIONS, true)) {
            $type = '';
        }

        $userId = $request->query('user_id');

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_ids' => $request->queryIntList('account_ids'),
            'category_ids' => $request->queryIntList('category_ids'),
            'tag_ids' => $request->queryIntList('tag_ids'),
            'type' => $type,
            'user_id' => $userId !== '' ? (int) $userId : null,
        ];
    }

    private function resolveGroupBy(string $value): string
    {
        return in_array($value, self::GROUP_OPTIONS, true) ? $value : 'category';
    }

    private function resolveSort(string $value): ?string
    {
        return in_array($value, ReportingService::REPORT_SORTABLE_FIELDS, true) ? $value : null;
    }
}
