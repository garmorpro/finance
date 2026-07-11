<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryGroupRepository;
use App\Repositories\CategoryRepository;
use App\Support\Csrf;
use App\Support\View;

final class CategoryController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $categoryRepo = new CategoryRepository();
        $all = $categoryRepo->listForHousehold($householdId, true);
        $groupRepo = new CategoryGroupRepository();

        $expenseGroups = $groupRepo->listForHousehold($householdId, 'expense');
        $incomeGroups = $groupRepo->listForHousehold($householdId, 'income');
        $expenseCategories = array_values(array_filter($all, fn (array $c): bool => $c['type'] === 'expense'));
        $incomeCategories = array_values(array_filter($all, fn (array $c): bool => $c['type'] === 'income'));

        Response::html(View::render('settings/categories', [
            'expenseSections' => $this->buildSections($expenseCategories, $expenseGroups),
            'incomeSections' => $this->buildSections($incomeCategories, $incomeGroups),
            'expenseGroups' => $expenseGroups,
            'incomeGroups' => $incomeGroups,
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice'], $_SESSION['_flash_old']);
    }

    /**
     * Groups a flat category list under their category_groups, with an
     * "ungrouped" bucket (group: null) appended last only if it has members.
     *
     * @param array $categories
     * @param array $groups
     * @return list<array{group: array|null, categories: array}>
     */
    private function buildSections(array $categories, array $groups): array
    {
        $sections = [];

        foreach ($groups as $group) {
            $sections[(int) $group['id']] = ['group' => $group, 'categories' => []];
        }
        $sections[0] = ['group' => null, 'categories' => []];

        foreach ($categories as $category) {
            $groupId = $category['group_id'] !== null ? (int) $category['group_id'] : 0;
            if (!isset($sections[$groupId])) {
                $groupId = 0;
            }
            $sections[$groupId]['categories'][] = $category;
        }

        return array_values(array_filter(
            $sections,
            fn (array $section, int $key): bool => $key !== 0 || $section['categories'] !== [],
            ARRAY_FILTER_USE_BOTH
        ));
    }

    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /settings/categories');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $categoryRepo = new CategoryRepository();
        $categoryId = $categoryRepo->create($householdId, $input['name'], $input['type']);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category.created',
            'category',
            $categoryId,
            $request->ip(),
            ['name' => $input['name']]
        );

        $_SESSION['_flash_notice'] = "Category \"{$input['name']}\" added.";
        header('Location: /settings/categories');
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $categoryId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $categoryRepo = new CategoryRepository();
        $category = $categoryRepo->findById($categoryId, $householdId);

        if ($category === null) {
            Response::html('Category not found.', 404);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /settings/categories');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $groupId = $input['group_id'] !== null ? (int) $input['group_id'] : null;
        if ($groupId !== null) {
            $group = (new CategoryGroupRepository())->findById($groupId, $householdId);
            if ($group === null || $group['type'] !== $input['type']) {
                $redirectBack('Please choose a valid section for this category type.');
                return;
            }
        }

        $categoryRepo->update($categoryId, $householdId, $input['name'], $input['type'], $category['color'], $groupId);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category.updated',
            'category',
            $categoryId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Category updated.';
        header('Location: /settings/categories');
    }

    public function archive(Request $request): void
    {
        $this->changeStatus($request, 'archive');
    }

    public function restore(Request $request): void
    {
        $this->changeStatus($request, 'restore');
    }

    private function changeStatus(Request $request, string $action): void
    {
        AuthMiddleware::requireAuth();

        $categoryId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $categoryRepo = new CategoryRepository();
        $category = $categoryRepo->findById($categoryId, $householdId);

        if ($category === null) {
            Response::html('Category not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/categories');
            return;
        }

        if ($action === 'archive') {
            $categoryRepo->archive($categoryId, $householdId);
        } else {
            $categoryRepo->restore($categoryId, $householdId);
        }

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category.' . $action . 'd',
            'category',
            $categoryId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Category updated.';
        header('Location: /settings/categories');
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'id' => $request->post('id') !== '' ? $request->post('id') : null,
            'name' => trim($request->post('name')),
            'type' => $request->post('type'),
            'group_id' => $request->post('group_id') !== '' ? $request->post('group_id') : null,
        ];
    }

    private function validate(array $input): ?string
    {
        if ($input['name'] === '') {
            return 'Category name is required.';
        }

        if (!in_array($input['type'], ['income', 'expense'], true)) {
            return 'Please choose a valid category type.';
        }

        return null;
    }
}
