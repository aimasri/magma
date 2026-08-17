<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Title: Decoupled Database Schema & Migration Initializer
 *
 * Purpose:
 * - Discover and execute framework core and modular database schema files outside the HTTP request lifecycle.
 * - Track executed migrations in a `schema_migrations` metadata table to prevent redundant DDL execution.
 * - Eliminate runtime performance bottlenecks caused by executing `CREATE TABLE IF NOT EXISTS` during HTTP boot.
 *
 * Why / Why this design:
 * - Running schema checks and DDL modifications during web requests dramatically inflates Time To First Byte (TTFB) 
 *   and locks database system catalogs under high concurrency.
 * - Decoupling database migrations into a dedicated CLI pipeline guarantees isolated transactions, 
 *   deterministic version control, and horizontal zero-downtime deployment compatibility.
 *
 * Teaching notes:
 * - Scans `magma/database/`, `modules/[module]/database/`, and `app/database/` for `schema.sql` and `migrations/*.sql` files.
 * - Tracks applied migrations via the `schema_migrations` table.
 */
class SchemaInitializer
{
    /**
     * Database connection manager.
     */
    private DatabaseConnectionManager $dbManager;

    /**
     * Absolute base path to project root.
     */
    private string $rootDir;

    /**
     * Initializes the Schema Initializer.
     *
     * @param DatabaseConnectionManager $dbManager
     * @param string|null $rootDir Project root directory path.
     */
    public function __construct(DatabaseConnectionManager $dbManager, ?string $rootDir = null)
    {
        $this->dbManager = $dbManager;
        $this->rootDir = $rootDir ?? (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2));
    }

    /**
     * Discovers and runs all pending database schema definitions and migration scripts.
     *
     * Execution Flow:
     * 1. Ensure `schema_migrations` table exists.
     * 2. Query already applied migration names.
     * 3. Discover candidate SQL files across core, modules, and app.
     * 4. For each unapplied SQL file:
     *    a. Execute SQL file content within a managed transaction block.
     *    b. Record migration name in `schema_migrations`.
     *    c. Track as executed.
     * 5. Return array of newly executed migration filenames.
     *
     * @return array<int, string> List of executed migration names.
     * @throws RuntimeException If migration fails.
     */
    public function runMigrations(): array
    {
        $pdo = $this->dbManager->getWriteConnection();
        $this->ensureMigrationTableExists($pdo);

        $applied = $this->getAppliedMigrations($pdo);
        $candidates = $this->discoverSchemaFiles();

        $executed = [];

        foreach ($candidates as $identifier => $filePath) {
            if (in_array($identifier, $applied, true)) {
                continue;
            }

            $this->executeMigrationFile($pdo, $identifier, $filePath);
            $executed[] = $identifier;
        }

        return $executed;
    }

    /**
     * Executes a single SQL file against the database.
     *
     * @param string $filePath Absolute path to the SQL file.
     * @return void
     * @throws RuntimeException
     */
    public function executeSqlFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("SQL file [{$filePath}] does not exist.");
        }

        $sql = (string) file_get_contents($filePath);
        $pdo = $this->dbManager->getWriteConnection();
        $pdo->exec($sql);
    }

    /**
     * Guarantees the existence of the schema_migrations tracking table.
     *
     * @param PDO $pdo
     * @return void
     */
    private function ensureMigrationTableExists(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
            id SERIAL PRIMARY KEY,
            migration_name VARCHAR(255) UNIQUE NOT NULL,
            executed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );";
        $pdo->exec($sql);
    }

    /**
     * Retrieves all previously executed migration identifiers.
     *
     * @param PDO $pdo
     * @return array<int, string>
     */
    private function getAppliedMigrations(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT migration_name FROM schema_migrations ORDER BY id ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Discovers all core and module schema/migration SQL files in deterministic order.
     *
     * @return array<string, string> Keyed by migration identifier => absolute file path.
     */
    public function discoverSchemaFiles(): array
    {
        $files = [];

        // 1. Magma Core Schema
        $coreSchema = $this->rootDir . '/magma/database/schema.sql';
        if (file_exists($coreSchema)) {
            $files['core:schema.sql'] = $coreSchema;
        }

        // 2. Magma Core Migrations
        $coreMigrationsDir = $this->rootDir . '/magma/database/migrations';
        if (is_dir($coreMigrationsDir)) {
            $matched = glob($coreMigrationsDir . '/*.sql') ?: [];
            sort($matched);
            foreach ($matched as $file) {
                $files['core:migration:' . basename($file)] = $file;
            }
        }

        // 3. Modular Schemas & Migrations
        $modulesDir = $this->rootDir . '/modules';
        if (is_dir($modulesDir)) {
            $moduleFolders = glob($modulesDir . '/*', GLOB_ONLYDIR) ?: [];
            sort($moduleFolders);
            foreach ($moduleFolders as $modulePath) {
                $moduleName = basename($modulePath);
                
                $modSchema = $modulePath . '/database/schema.sql';
                if (file_exists($modSchema)) {
                    $files["module:{$moduleName}:schema.sql"] = $modSchema;
                }

                $modMigrations = glob($modulePath . '/database/migrations/*.sql') ?: [];
                sort($modMigrations);
                foreach ($modMigrations as $file) {
                    $files["module:{$moduleName}:migration:" . basename($file)] = $file;
                }
            }
        }

        // 4. App-Level Schemas & Migrations
        $appSchema = $this->rootDir . '/app/database/schema.sql';
        if (file_exists($appSchema)) {
            $files['app:schema.sql'] = $appSchema;
        }

        $appMigrationsDir = $this->rootDir . '/app/database/migrations';
        if (is_dir($appMigrationsDir)) {
            $appMigrations = glob($appMigrationsDir . '/*.sql') ?: [];
            sort($appMigrations);
            foreach ($appMigrations as $file) {
                $files['app:migration:' . basename($file)] = $file;
            }
        }

        return $files;
    }

    /**
     * Executes a migration file inside an atomic transaction block and registers its completion.
     *
     * @param PDO $pdo
     * @param string $identifier
     * @param string $filePath
     * @return void
     * @throws RuntimeException
     */
    private function executeMigrationFile(PDO $pdo, string $identifier, string $filePath): void
    {
        $sql = (string) file_get_contents($filePath);

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);

            $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration_name) VALUES (?)");
            $stmt->execute([$identifier]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Migration failed for [{$identifier}]: " . $e->getMessage(), 0, $e);
        }
    }
}
