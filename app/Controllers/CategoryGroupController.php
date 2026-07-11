<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryGroupRepository;
use App\Support\Csrf;

final class CategoryGroupController
{
    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $name = trim($request->post('name'));
        $type = $request->post('type');

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/categories');
            return;
        }

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Section name is required.';
            header('Location: /settings/categories');
            return;
        }

        if (!in_array($type, ['income', 'expense'], true)) {
            $_SESSION['_flash_error'] = 'Please choose a valid section type.';
            header('Location: /settings/categories');
            return;
        }

        $groupRepo = new CategoryGroupRepository();
        $groupId = $groupRepo->create($householdId, $name, $type);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category_group.created',
            'category_group',
            $groupId,
            $request->ip(),
            ['name' => $name, 'type' => $type]
        );

        $_SESSION['_flash_notice'] = "Section \"{$name}\" added.";
        header('Location: /settings/categories');
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $groupId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $name = trim($request->post('name'));

        $groupRepo = new CategoryGroupRepository();
        $group = $groupRepo->findById($groupId, $householdId);

        if ($group === null) {
            Response::html('Section not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/categories');
            return;
        }

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Section name is required.';
            header('Location: /settings/categories');
            return;
        }

        $groupRepo->rename($groupId, $householdId, $name);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category_group.renamed',
            'category_group',
            $groupId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Section renamed.';
        header('Location: /settings/categories');
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $groupId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $groupRepo = new CategoryGroupRepository();
        $group = $groupRepo->findById($groupId, $householdId);

        if ($group === null) {
            Response::html('Section not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/categories');
            return;
        }

        $groupRepo->delete($groupId, $householdId);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'category_group.deleted',
            'category_group',
            $groupId,
            $request->ip(),
            ['name' => $group['name']]
        );

        $_SESSION['_flash_notice'] = "Section \"{$group['name']}\" deleted. Its categories are now ungrouped.";
        header('Location: /settings/categories');
    }
}
