#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Title: Magma CLI Database Migration Runner
 *
 * Purpose:
 * - Standalone CLI command to discover and run pending database migrations and schemas.
 * - Executes DDL migrations outside the HTTP request lifecycle.
 *
 * Why / Why this design:
 * - Decoupling database schema execution from web request bootstrapping eliminates 
 *   per-request DDL execution, reduces TTFB to the absolute minimum, and allows safe 
 *   multi-worker continuous deployment pipelines.
 *
 * Teaching notes:
 * - Run via CLI: `php bin/migrate.php`
 * - Returns exit code 0 on success, exit code 1 on failure.
 */

require_once __DIR__ . '/../magma/config/bootstrap.php';

use Magma\database\DatabaseConnectionManager;
use Magma\database\SchemaInitializer;

echo "====================================================\n";
echo " Magma Framework - Database Migration Runner\n";
echo "====================================================\n\n";

try {
    /** @var DatabaseConnectionManager $dbManager */
    $dbManager = $container->get(DatabaseConnectionManager::class);
    $initializer = new SchemaInitializer($dbManager, ROOT_DIR);

    echo "[*] Discovering schema and migration files...\n";
    $candidates = $initializer->discoverSchemaFiles();
    echo "[*] Found " . count($candidates) . " total schema/migration definitions.\n\n";

    echo "[*] Executing pending migrations...\n";
    $executed = $initializer->runMigrations();

    if (empty($executed)) {
        echo "\e[32m[OK] Database is already up to date. No pending migrations.\e[0m\n\n";
    } else {
        foreach ($executed as $migration) {
            echo "\e[32m[✔] Migrated:\e[0m {$migration}\n";
        }
        echo "\n\e[32m[OK] Successfully executed " . count($executed) . " migration(s).\e[0m\n\n";
    }

    exit(0);
} catch (\Throwable $e) {
    echo "\n\e[31m[ERROR] Migration execution failed:\e[0m\n";
    echo $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}
