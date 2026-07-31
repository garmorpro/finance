<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryGroupRepository;
use App\Repositories\CategoryRepository;
use App\Services\CategoryMergeService;
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
            'expenseParentOptions' => $this->eligibleParents($expenseCategories),
            'incomeParentOptions' => $this->eligibleParents($incomeCategories),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice'], $_SESSION['_flash_old']);
    }

    /**
     * Buckets categories of one type into their category_groups (an
     * "Ungrouped" bucket last, only if it has members), with subcategories
     * nested under their parent instead of appearing again as their own
     * row, and each group's top-level categories split into active/
     * archived so an archived category no longer just sits dimmed inline
     * among active ones. An archived parent's children move to the
     * archived list together with it, regardless of the child's own
     * status — a nested row always follows its parent.
     *
     * @param array $categories
     * @param array $groups
     * @return list<array{group: array|null, active: list<array{category: array, children: array}>, archived: list<array{category: array, children: array}>}>
     */
    private function buildSections(array $categories, array $groups): array
    {
        $topLevel = [];
        $childrenByParent = [];
        foreach ($categories as $category) {
            if ($category['parent_category_id'] !== null) {
                $childrenByParent[(int) $category['parent_category_id']][] = $category;
            } else {
                $topLevel[] = $category;
            }
        }

        $sections = [];
        foreach ($groups as $group) {
            $sections[(int) $group['id']] = ['group' => $group, 'active' => [], 'archived' => []];
        }
        $sections[0] = ['group' => null, 'active' => [], 'archived' => []];

        foreach ($topLevel as $category) {
            $groupId = $category['group_id'] !== null ? (int) $category['group_id'] : 0;
            if (!isset($sections[$groupId])) {
                $groupId = 0;
            }

            $row = [
                'category' => $category,
                'children' => $childrenByParent[(int) $category['id']] ?? [],
            ];

            $bucket = $category['archived_at'] !== null ? 'archived' : 'active';
            $sections[$groupId][$bucket][] = $row;
        }

        return array_values(array_filter(
            $sections,
            fn (array $section, int $key): bool => $key !== 0 || $section['active'] !== [] || $section['archived'] !== [],
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Eligible "parent category" choices for a create/edit dropdown: only
     * top-level categories (not already a subcategory themselves), not
     * archived, and without children of their own — this app supports one
     * level of nesting, not arbitrary depth.
     */
    private function eligibleParents(array $categoriesOfType): array
    {
        $parentIds = [];
        foreach ($categoriesOfType as $c) {
            if ($c['parent_category_id'] !== null) {
                $parentIds[(int) $c['parent_category_id']] = true;
            }
        }

        return array_values(array_filter(
            $categoriesOfType,
            fn (array $c): bool => $c['parent_category_id'] === null
                && $c['archived_at'] === null
                && !isset($parentIds[(int) $c['id']])
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

        [$groupId, $parentId, $groupError] = $this->resolveGroupAndParent($input, $householdId);
        if ($groupError !== null) {
            $redirectBack($groupError);
            return;
        }

        $categoryId = (new CategoryRepository())->create($householdId, $input['name'], $input['type'], $input['color'], $parentId, $groupId);

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

        [$groupId, $parentId, $groupError] = $this->resolveGroupAndParent($input, $householdId, $categoryId);
        if ($groupError !== null) {
            $redirectBack($groupError);
            return;
        }

        $categoryRepo->update($categoryId, $householdId, $input['name'], $input['type'], $input['color'], $groupId, $parentId);

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

    /**
     * Drag-and-drop reordering within one group's category list — the
     * same "POST the new order as a JSON array of ids, scoped defensively
     * server-side" pattern the dashboard's tile reordering already uses.
     * CategoryRepository::reorder() itself only ever touches rows that
     * belong to this household, so a tampered id list just fails to match
     * anything rather than reordering another household's categories.
     */
    public function reorder(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $decoded = json_decode($request->post('order'), true);
        if (!is_array($decoded) || array_filter($decoded, fn ($v): bool => !is_int($v) && !ctype_digit((string) $v)) !== []) {
            Response::json(['error' => 'Invalid order data.'], 422);
            return;
        }

        (new CategoryRepository())->reorder($householdId, array_map('intval', array_values($decoded)));

        Response::json(['status' => 'ok']);
    }

    public function showMergeForm(Request $request): void
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

        $otherCategories = array_values(array_filter(
            $categoryRepo->listForHousehold($householdId),
            fn (array $c): bool => (int) $c['id'] !== $categoryId && $c['type'] === $category['type']
        ));

        $targetQuery = $request->query('target_id');
        $target = null;
        $targetError = null;
        $preview = null;

        if ($targetQuery !== '') {
            $targetId = (int) $targetQuery;
            $target = $categoryRepo->findById($targetId, $householdId);

            if ($target === null || $target['type'] !== $category['type'] || $targetId === $categoryId) {
                $targetError = 'Please choose a valid category of the same type to merge into.';
                $target = null;
            } else {
                $preview = (new CategoryMergeService())->preview($categoryId, $householdId);
            }
        }

        Response::html(View::render('settings/category-merge', [
            'category' => $category,
            'otherCategories' => $otherCategories,
            'target' => $target,
            'targetError' => $targetError,
            'preview' => $preview,
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function mergeCategories(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $categoryId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $categoryRepo = new CategoryRepository();
        $category = $categoryRepo->findById($categoryId, $householdId);

        if ($category === null) {
            Response::html('Category not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/categories/' . $categoryId . '/merge');
            return;
        }

        $targetId = (int) $request->post('target_id');
        $target = $categoryRepo->findById($targetId, $householdId);

        if ($target === null || $target['type'] !== $category['type'] || $targetId === $categoryId) {
            $_SESSION['_flash_error'] = 'Please choose a valid category of the same type to merge into.';
            header('Location: /settings/categories/' . $categoryId . '/merge');
            return;
        }

        (new CategoryMergeService())->merge($categoryId, $targetId, $householdId);

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'category.merged',
            'category',
            $categoryId,
            $request->ip(),
            ['from' => $category['name'], 'into' => $target['name']]
        );

        $_SESSION['_flash_notice'] = "Merged \"{$category['name']}\" into \"{$target['name']}\".";
        header('Location: /settings/categories');
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
            'color' => $request->post('color') !== '' ? $request->post('color') : null,
            'group_id' => $request->post('group_id') !== '' ? $request->post('group_id') : null,
            'parent_category_id' => $request->post('parent_category_id') !== '' ? $request->post('parent_category_id') : null,
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

        if ($input['color'] !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $input['color']) !== 1) {
            return 'Please choose a valid color.';
        }

        return null;
    }

    /**
     * Validates group_id and parent_category_id together and applies the
     * "a subcategory always lives in its parent's own section" rule — if
     * a parent is chosen, the parent's own group_id wins over whatever
     * was submitted, so a subcategory can never end up filed under a
     * different group card than the category it nests under.
     *
     * $currentCategoryId is null when creating (nothing to guard against
     * yet) and the category's own id when editing (guards against making
     * a category its own parent, or a parent of something that already
     * has children of its own).
     *
     * @return array{0: ?int, 1: ?int, 2: ?string} [resolvedGroupId, resolvedParentId, error]
     */
    private function resolveGroupAndParent(array $input, int $householdId, ?int $currentCategoryId = null): array
    {
        $groupId = $input['group_id'] !== null ? (int) $input['group_id'] : null;
        $parentId = $input['parent_category_id'] !== null ? (int) $input['parent_category_id'] : null;

        if ($parentId !== null) {
            if ($parentId === $currentCategoryId) {
                return [null, null, "A category can't be its own parent."];
            }

            $categoryRepo = new CategoryRepository();
            $parent = $categoryRepo->findById($parentId, $householdId);

            if ($parent === null || $parent['archived_at'] !== null) {
                return [null, null, 'Please choose a valid parent category.'];
            }

            if ($parent['type'] !== $input['type']) {
                return [null, null, 'The parent category must be the same type (income or expense).'];
            }

            if ($parent['parent_category_id'] !== null) {
                return [null, null, "That category is already a subcategory — only one level of nesting is supported."];
            }

            if ($currentCategoryId !== null && $categoryRepo->hasChildren($currentCategoryId, $householdId)) {
                return [null, null, "This category has its own subcategories, so it can't become one itself."];
            }

            $groupId = $parent['group_id'] !== null ? (int) $parent['group_id'] : null;
        } elseif ($groupId !== null) {
            $group = (new CategoryGroupRepository())->findById($groupId, $householdId);
            if ($group === null || $group['type'] !== $input['type']) {
                return [null, null, 'Please choose a valid section for this category type.'];
            }
        }

        return [$groupId, $parentId, null];
    }
}
