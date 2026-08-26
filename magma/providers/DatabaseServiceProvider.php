<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\database\DatabaseConnectionManager;
use Magma\database\DatabaseTransactionManager;
use Magma\database\TransactionManagerInterface;
use Magma\database\SchemaInitializer;
use Magma\config\Config;

/**
 * Title: DatabaseServiceProvider
 *
 * Purpose:
 * - Bootstraps database connection managers, read/write PDO instances, transaction coordinators, and schema runners
 * - Registers separate read and write database connections for potential replica setups
 * - Binds the TransactionManagerInterface to its concrete implementation
 *
 * Why / Why this design:
 * - Service Provider Pattern: Keeps the application kernel decoupled and modular by centralizing DB configuration
 * - Separation of Concerns: Handles complex connection setup (like read/write splitting) away from the business logic
 *
 * Teaching notes:
 * - Notice how `db.write` and `db.read` are separated; this allows for easy horizontal scaling using read replicas.
 */
class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers database connection and transaction services in the DI container.
     *
     * 1. Binds DatabaseConnectionManager using database and replica configurations.
     * 2. Registers specific aliases ('db.write', 'db.read') for direct connection access.
     * 3. Binds TransactionManagerInterface and DatabaseTransactionManager.
     * 4. Sets up the SchemaInitializer for potential migration running.
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(DatabaseConnectionManager::class, function ($c) {
            /** @var array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} $primary */
            $primary = Config::getDatabaseSettings();
            /** @var array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} $replica */
            $replica = Config::getReplicaDatabaseSettings();

            return new DatabaseConnectionManager(
                $primary,
                $replica,
                Config::get('DB_EMULATE_PREPARES', 'false') === 'true'
            );
        });

        $container->set('db.write', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getWriteConnection();
        });

        $container->set('db.read', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getReadConnection();
        });

        $container->bind(TransactionManagerInterface::class, DatabaseTransactionManager::class);

        $container->set(DatabaseTransactionManager::class, function ($c) {
            $manager = $c->get(DatabaseConnectionManager::class);
            assert($manager instanceof DatabaseConnectionManager);
            return new DatabaseTransactionManager($manager);
        });

        $container->set(SchemaInitializer::class, function ($c) {
            $manager = $c->get(DatabaseConnectionManager::class);
            assert($manager instanceof DatabaseConnectionManager);
            return new SchemaInitializer($manager, defined('ROOT_DIR') ? ROOT_DIR : null);
        });
    }
}
