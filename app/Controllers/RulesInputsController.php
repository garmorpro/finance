<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Repositories\TransactionRuleRepository;
use App\Support\Csrf;
use App\Support\View;

/**
 * A single "Rules & Inputs" entry point covering Transaction Rules,
 * Categories, and Tags — three distinct, unchanged features (their own
 * controllers, routes, and full settings pages still exist) presented
 * together as one hub with a short preview of each and a link through to
 * the full page. Mirrors PlanningController's Recurring+Goals pattern.
 */
final class RulesInputsController
{
    private const PREVIEW_LIMIT = 6;

    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $rules = (new TransactionRuleRepository())->listForHousehold($householdId);
        $activeRuleCount = count(array_filter($rules, fn (array $r): bool => (int) $r['is_active'] === 1));

        $categories = (new CategoryRepository())->listForHousehold($householdId);
        $incomeCategoryCount = count(array_filter($categories, fn (array $c): bool => $c['type'] === 'income'));
        $expenseCategoryCount = count(array_filter($categories, fn (array $c): bool => $c['type'] === 'expense'));

        $tags = (new TagRepository())->listForHousehold($householdId);

        Response::html(View::render('rules-inputs/index', [
            'rules' => array_slice($rules, 0, self::PREVIEW_LIMIT),
            'ruleCount' => count($rules),
            'activeRuleCount' => $activeRuleCount,
            'pausedRuleCount' => count($rules) - $activeRuleCount,
            'categories' => array_slice($categories, 0, self::PREVIEW_LIMIT),
            'categoryCount' => count($categories),
            'incomeCategoryCount' => $incomeCategoryCount,
            'expenseCategoryCount' => $expenseCategoryCount,
            'tags' => array_slice($tags, 0, self::PREVIEW_LIMIT + 6),
            'tagCount' => count($tags),
            'csrfToken' => Csrf::token(),
        ]));
    }
}
