<?php

declare(strict_types=1);

use App\Database\Connection;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// APP_ENV=testing php bin/migrate.php applies migrations to the test
// database instead of production — used by `composer test` / CI, and
// for setting up a fresh test database by hand. See docs/testing.md.
$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
Dotenv::createImmutable(dirname(__DIR__), $envFile)->safeLoad();

$pdo = Connection::get();

$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$migrationsDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    $sql = file_get_contents($file);

    echo "Applying {$name}... ";

    try {
        // MySQL DDL statements (CREATE TABLE, etc.) commit implicitly, so an
        // explicit transaction here would not actually make this atomic —
        // it would only make failures harder to diagnose.
        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO migrations (migration, applied_at) VALUES (:migration, :applied_at)');
        $stmt->execute([
            'migration' => $name,
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);

        echo "done.\n";
        $ran++;
    } catch (\Throwable $e) {
        echo "FAILED: {$e->getMessage()}\n";
        exit(1);
    }
}

if ($ran === 0) {
    echo "Nothing to migrate. Already up to date.\n";
} else {
    echo "{$ran} migration(s) applied.\n";
}
