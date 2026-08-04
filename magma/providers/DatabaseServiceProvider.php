<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\database\DatabaseConnectionManager;
use Magma\config\Config;

/**
 * Title: Database Service Provider
 * Purpose: Bootstraps the DatabaseConnectionManager and PDO connections.
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
    }
}
