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
 * Title: Database Service Provider
 *
 * Purpose:
 * - Bootstraps database connection managers, read/write PDO instances, transaction coordinators, and schema runners.
 *
 * Why / Why this design:
 * - Centralizes database infrastructure configuration in a dedicated Service Provider, 
 *   keeping the application kernel decoupled and modular.
 */
class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(DatabaseConnectionManager::class, function ($c) {
            return new DatabaseConnectionManager(
                Config::getDatabaseSettings(),
                Config::getReplicaDatabaseSettings(),
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
            return new DatabaseTransactionManager($c->get(DatabaseConnectionManager::class));
        });

        $container->set(SchemaInitializer::class, function ($c) {
            return new SchemaInitializer($c->get(DatabaseConnectionManager::class), defined('ROOT_DIR') ? ROOT_DIR : null);
        });
    }
}
