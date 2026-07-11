<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\CategoryRepository;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$pdo = Connection::get();

$households = $pdo->query(
    'SELECT h.id, h.name FROM households h
     WHERE h.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM categories c WHERE c.household_id = h.id)'
)->fetchAll();

if ($households === []) {
    echo "Every household already has categories. Nothing to do.\n";
    exit(0);
}

$categoryRepo = new CategoryRepository();

foreach ($households as $household) {
    $categoryRepo->seedDefaults((int) $household['id']);
    echo "Seeded default categories for \"{$household['name']}\".\n";
}
