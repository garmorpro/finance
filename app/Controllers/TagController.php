<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\TagRepository;
use App\Support\Csrf;
use App\Support\View;

final class TagController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render('settings/tags', [
            'tags' => (new TagRepository())->listForHouseholdWithUsage($householdId),
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
            header('Location: /settings/tags');
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

        $tagRepo = new TagRepository();

        if ($tagRepo->findByName($householdId, $input['name']) !== null) {
            $redirectBack('A tag with that name already exists.');
            return;
        }

        $tagId = $tagRepo->create($householdId, $input['name'], $input['color']);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'tag.created',
            'tag',
            $tagId,
            $request->ip(),
            ['name' => $input['name']]
        );

        $_SESSION['_flash_notice'] = "Tag \"{$input['name']}\" added.";
        header('Location: /settings/tags');
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $tagId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $tagRepo = new TagRepository();
        $tag = $tagRepo->findById($tagId, $householdId);

        if ($tag === null) {
            Response::html('Tag not found.', 404);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /settings/tags');
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

        $existing = $tagRepo->findByName($householdId, $input['name']);
        if ($existing !== null && (int) $existing['id'] !== $tagId) {
            $redirectBack('A tag with that name already exists.');
            return;
        }

        $tagRepo->update($tagId, $householdId, $input['name'], $input['color']);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'tag.updated',
            'tag',
            $tagId,
            $request->ip()
        );

        $_SESSION['_flash_notice'] = 'Tag updated.';
        header('Location: /settings/tags');
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $tagId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $tagRepo = new TagRepository();
        $tag = $tagRepo->findById($tagId, $householdId);

        if ($tag === null) {
            Response::html('Tag not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/tags');
            return;
        }

        $tagRepo->delete($tagId, $householdId);

        (new AuditLogRepository())->log(
            (int) AuthMiddleware::userId(),
            $householdId,
            'tag.deleted',
            'tag',
            $tagId,
            $request->ip(),
            ['name' => $tag['name']]
        );

        $_SESSION['_flash_notice'] = "Tag \"{$tag['name']}\" deleted.";
        header('Location: /settings/tags');
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'id' => $request->post('id') !== '' ? $request->post('id') : null,
            'name' => trim($request->post('name')),
            'color' => trim($request->post('color')) !== '' ? trim($request->post('color')) : null,
        ];
    }

    private function validate(array $input): ?string
    {
        if ($input['name'] === '') {
            return 'Tag name is required.';
        }

        if (mb_strlen($input['name']) > 50) {
            return 'Tag name must be 50 characters or fewer.';
        }

        if ($input['color'] !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'])) {
            return 'Color must be a hex value like #e2694b.';
        }

        return null;
    }
}
