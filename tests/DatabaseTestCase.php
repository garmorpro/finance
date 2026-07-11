<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that touch the database. Every test runs inside a
 * transaction that's rolled back in tearDown(), so the test database is
 * never left with residue between tests or between runs.
 *
 * Scope note: this only covers repository/service-level tests, not full
 * controller flows. Several controllers (TransactionController,
 * BudgetController, GoalController, RecurringController) open their own
 * PDO transaction around a multi-step write — nesting that inside this
 * class's own wrapping transaction would throw ("There is already an
 * active transaction"), since this codebase doesn't use SAVEPOINTs.
 * Testing at the repository layer (calling the same methods a controller
 * calls, in the same order) sidesteps that without needing to change how
 * the application code manages transactions.
 *
 * Skips — never fails — when no test database is configured, so
 * `composer test` still runs the pure unit test suite on a machine with
 * no database set up at all.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $database = $_ENV['DB_DATABASE'] ?? '';

        if ($database === '') {
            self::markTestSkipped(
                'No test database configured. Copy .env.testing.example to .env.testing, point it at a ' .
                'dedicated test database, run "APP_ENV=testing php bin/migrate.php", then re-run the suite. ' .
                'See docs/testing.md.'
            );
        }

        if (!str_contains(strtolower($database), 'test')) {
            self::markTestSkipped(
                "Refusing to run against database \"{$database}\" — DB_DATABASE in .env.testing must contain " .
                '"test" as a safety check against accidentally pointing this suite at production.'
            );
        }

        self::$pdo = Connection::get();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
    }
}
