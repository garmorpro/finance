<?php

declare(strict_types=1);

use App\Database\Connection;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * One-time data migration, not a schema migration (it belongs in bin/,
 * not database/migrations/, because it's DML that needs an explicit
 * transaction per household — the migrations runner intentionally
 * doesn't wrap statements in a transaction since DDL commits implicitly
 * anyway; this script's UPDATEs actually need that safety).
 *
 * For every category_group that has 2+ top-level, non-archived, childless
 * categories: creates a new category named after the group (a real
 * parent category, not just a section label) and reparents those
 * existing categories under it.
 *
 * A category that already has its own children is left alone — this app
 * only supports one level of subcategory nesting, so it can't also
 * become a child of the new group-parent. A group where a category
 * already shares the group's exact name is skipped entirely (ambiguous
 * whether that's meant to already BE the parent) and reported so it can
 * be handled by hand.
 *
 * Defaults to a dry run — prints exactly what it would do without
 * writing anything. Pass --apply to actually make the changes.
 *   php bin/promote-groups-to-parent-categories.php
 *   php bin/promote-groups-to-parent-categories.php --apply
 */

$apply = in_array('--apply', $argv, true);

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$pdo = Connection::get();

$households = $pdo->query('SELECT id, name FROM households ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$totalCreated = 0;
$totalReparented = 0;

foreach ($households as $household) {
    $householdId = (int) $household['id'];

    $groups = $pdo->prepare('SELECT id, name, type FROM category_groups WHERE household_id = :household_id ORDER BY type, sort_order');
    $groups->execute(['household_id' => $householdId]);
    $groups = $groups->fetchAll(PDO::FETCH_ASSOC);

    if ($groups === []) {
        continue;
    }

    echo "== Household #{$householdId} ({$household['name']}) ==\n";

    foreach ($groups as $group) {
        $groupId = (int) $group['id'];
        $groupName = $group['name'];
        $groupType = $group['type'];

        // Every non-archived category currently in this group, so we can
        // tell top-level from subcategory and find name collisions.
        $stmt = $pdo->prepare(
            'SELECT id, name, parent_category_id
             FROM categories
             WHERE household_id = :household_id AND group_id = :group_id AND archived_at IS NULL'
        );
        $stmt->execute(['household_id' => $householdId, 'group_id' => $groupId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasNameCollision = false;
        $childOfId = []; // parent_category_id => true, for the "has children" check
        foreach ($members as $m) {
            if (strcasecmp(trim($m['name']), trim($groupName)) === 0) {
                $hasNameCollision = true;
            }
            if ($m['parent_category_id'] !== null) {
                $childOfId[(int) $m['parent_category_id']] = true;
            }
        }

        if ($hasNameCollision) {
            echo "  [{$groupType}] \"{$groupName}\": skipped — a category with this exact name already exists in this section. Handle by hand.\n";
            continue;
        }

        $eligible = [];
        $ineligible = [];
        foreach ($members as $m) {
            if ($m['parent_category_id'] !== null) {
                continue; // already a subcategory of something else
            }
            if (isset($childOfId[(int) $m['id']])) {
                $ineligible[] = $m['name']; // already has children of its own
                continue;
            }
            $eligible[] = $m;
        }

        if (count($eligible) < 2) {
            echo "  [{$groupType}] \"{$groupName}\": skipped — fewer than 2 categories eligible to nest.\n";
            continue;
        }

        $names = implode(', ', array_map(fn (array $m): string => $m['name'], $eligible));
        echo "  [{$groupType}] \"{$groupName}\": create as parent category, move under it: {$names}";
        if ($ineligible !== []) {
            echo ' (left as-is, already has its own subcategories: ' . implode(', ', $ineligible) . ')';
        }
        echo "\n";

        if (!$apply) {
            continue;
        }

        $pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');

            $insert = $pdo->prepare(
                'INSERT INTO categories (household_id, parent_category_id, group_id, name, type, color, sort_order, created_at, updated_at)
                 VALUES (:household_id, NULL, :group_id, :name, :type, NULL, 0, :created_at, :updated_at)'
            );
            $insert->execute([
                'household_id' => $householdId,
                'group_id' => $groupId,
                'name' => $groupName,
                'type' => $groupType,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newParentId = (int) $pdo->lastInsertId();

            $reparent = $pdo->prepare(
                'UPDATE categories SET parent_category_id = :parent_id, updated_at = :updated_at
                 WHERE id = :id AND household_id = :household_id'
            );
            foreach ($eligible as $m) {
                $reparent->execute([
                    'parent_id' => $newParentId,
                    'updated_at' => $now,
                    'id' => (int) $m['id'],
                    'household_id' => $householdId,
                ]);
                $totalReparented++;
            }

            $pdo->commit();
            $totalCreated++;
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
    echo "{$totalCreated} parent categor" . ($totalCreated === 1 ? 'y' : 'ies') . " created, {$totalReparented} categor" . ($totalReparented === 1 ? 'y' : 'ies') . " reparented.\n";
}
