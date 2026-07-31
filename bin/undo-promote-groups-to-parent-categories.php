<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Services\CategoryMergeService;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Reverses bin/promote-groups-to-parent-categories.php: naming the new
 * parent category identically to its own section turned out to look
 * like a duplicate label in the UI ("Housing" section header, then a
 * "Housing" card right under it) rather than a real hierarchy — this
 * undoes that specific mistake, not a general-purpose category-delete
 * tool.
 *
 * For every category_group: finds a top-level, non-archived category
 * whose name exactly matches the group's own name (i.e. the thing the
 * promotion script created), moves its children back to top-level
 * (parent_category_id = NULL, same as before the promotion ran), and
 * removes that now-empty category — hard-deleted if nothing real ever
 * got attached to it directly, archived instead if it did (so we never
 * silently discard real data on a guess).
 *
 * Defaults to a dry run — prints exactly what it would do without
 * writing anything. Pass --apply to actually make the changes.
 *   php bin/undo-promote-groups-to-parent-categories.php
 *   php bin/undo-promote-groups-to-parent-categories.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$pdo = Connection::get();
$mergeService = new CategoryMergeService();

$households = $pdo->query('SELECT id, name FROM households ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$totalRemoved = 0;
$totalArchived = 0;
$totalReparented = 0;

foreach ($households as $household) {
    $householdId = (int) $household['id'];

    $groups = $pdo->prepare('SELECT id, name, type FROM category_groups WHERE household_id = :household_id ORDER BY type, sort_order');
    $groups->execute(['household_id' => $householdId]);
    $groups = $groups->fetchAll(PDO::FETCH_ASSOC);

    if ($groups === []) {
        continue;
    }

    $printedHouseholdHeader = false;

    foreach ($groups as $group) {
        $groupId = (int) $group['id'];
        $groupName = $group['name'];
        $groupType = $group['type'];

        $stmt = $pdo->prepare(
            'SELECT id, name FROM categories
             WHERE household_id = :household_id AND group_id = :group_id AND type = :type
               AND parent_category_id IS NULL AND archived_at IS NULL AND LOWER(TRIM(name)) = LOWER(TRIM(:name))
             LIMIT 1'
        );
        $stmt->execute([
            'household_id' => $householdId,
            'group_id' => $groupId,
            'type' => $groupType,
            'name' => $groupName,
        ]);
        $root = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($root === false) {
            continue;
        }

        $rootId = (int) $root['id'];

        $childStmt = $pdo->prepare('SELECT id, name FROM categories WHERE parent_category_id = :parent_id AND household_id = :household_id');
        $childStmt->execute(['parent_id' => $rootId, 'household_id' => $householdId]);
        $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);

        $preview = $mergeService->preview($rootId, $householdId);
        $hasDirectUsage = $preview['transactions'] > 0
            || $preview['splits'] > 0
            || $preview['recurringItems'] > 0
            || $preview['ruleActions'] > 0
            || $preview['budgetItems'] > 0;

        if (!$printedHouseholdHeader) {
            echo "== Household #{$householdId} ({$household['name']}) ==\n";
            $printedHouseholdHeader = true;
        }

        $childNames = implode(', ', array_map(fn (array $c): string => $c['name'], $children));
        $action = $hasDirectUsage ? 'archive it (has real data attached, not deleting)' : 'delete it';
        echo "  [{$groupType}] \"{$groupName}\": move back to top-level: {$childNames}; then {$action}.\n";

        if (!$apply) {
            continue;
        }

        $pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');

            $reparent = $pdo->prepare(
                'UPDATE categories SET parent_category_id = NULL, updated_at = :updated_at
                 WHERE id = :id AND household_id = :household_id'
            );
            foreach ($children as $c) {
                $reparent->execute([
                    'updated_at' => $now,
                    'id' => (int) $c['id'],
                    'household_id' => $householdId,
                ]);
                $totalReparented++;
            }

            if ($hasDirectUsage) {
                $archive = $pdo->prepare('UPDATE categories SET archived_at = :archived_at, updated_at = :updated_at WHERE id = :id AND household_id = :household_id');
                $archive->execute([
                    'archived_at' => $now,
                    'updated_at' => $now,
                    'id' => $rootId,
                    'household_id' => $householdId,
                ]);
                $totalArchived++;
            } else {
                $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id AND household_id = :household_id');
                $delete->execute(['id' => $rootId, 'household_id' => $householdId]);
                $totalRemoved++;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo "  FAILED: {$e->getMessage()}\n";
        }
    }
}

echo "\n";
if (!$apply) {
    echo "Dry run only — nothing was changed. Re-run with --apply to make these changes.\n";
} else {
    echo "{$totalReparented} categor" . ($totalReparented === 1 ? 'y' : 'ies') . " moved back to top-level, {$totalRemoved} deleted, {$totalArchived} archived.\n";
}
