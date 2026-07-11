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

        Response::html(View::render('settings/categories', [
            'expenseCategories' => array_values(array_filter($all, fn (array $c): bool => $c['type'] === 'expense')),
            'incomeCategories' => array_values(array_filter($all, fn (array $c): bool => $c['type'] === 'income')),
            'expenseGroups' => $groupRepo->listForHousehold($householdId, 'expense'),
            'incomeGroups' => $groupRepo->listForHousehold($householdId, 'income'),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice'], $_SESSION['_flash_old']);
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
        $categoryId = $categoryRepo->create($householdId, $input['name'], $input['type'], $input['color']);

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

        $categoryRepo->update($categoryId, $householdId, $input['name'], $input['type'], $input['color'], $groupId);

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
            'color' => trim($request->post('color')) !== '' ? trim($request->post('color')) : null,
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

        if ($input['color'] !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'])) {
            return 'Color must be a hex value like #e2694b.';
        }

        return null;
    }
}
