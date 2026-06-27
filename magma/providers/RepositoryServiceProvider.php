<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\database\TransactionManagerInterface;
use Magma\database\DatabaseTransactionManager;

use Magma\models\VendorRepository;
use Magma\models\VendorRepositoryInterface;
use Magma\models\CachedVendorRepository;
use Magma\models\SiteReviewRepository;
use Magma\models\UserRepository;
use Magma\models\UserRepositoryInterface;
use Magma\models\UserTokenRepository;


/**
 * RepositoryServiceProvider — registers all database repository abstractions.
 *
 * Purpose:
 * - Bootstraps the application's data access layer by binding interfaces to concrete SQL implementations.
 * - Injects the shared PDO instance into each repository.
 *
 * Why / Why this design:
 * - Isolating repositories into their own provider ensures the data access layer 
 *   can be tested and swapped out independently of domain services and controllers. 
 *   It strictly enforces the Open/Closed Principle.
 *
 * Teaching notes:
 * - Notice how we bind `VendorRepositoryInterface` to an `InMemoryVendorRepository` decorator 
 *   that wraps a Redis-caching decorator, which wraps the base SQL repository. This is the 
 *   Decorator Pattern in action!
 */
class RepositoryServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Data Access Bindings
     *
     * Execution Flow:
     * 1. Bind complex decorated repositories (e.g., VendorRepository).
     * 2. Bind standard SQL repositories using the `db.write` and `db.read` PDO connections.
     * 3. Bind the global database TransactionManager.
     *
     * Logic behind the logic:
     * - By resolving PDO via `$c->get('db.write')`, we ensure that all repositories share the 
     *   exact same connection instance, preventing max connection exhaustion.
     *
     * @param Container $container The global dependency injection container.
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(VendorRepositoryInterface::class, function ($c) {
            $baseRepo = new VendorRepository(
                $c->get('db.write'),
                $c->get('db.read'),
                new \Magma\models\VendorMapper(),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            $redisRepo = new CachedVendorRepository(
                $baseRepo,
                $c->get(\Redis::class),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            return new \Magma\models\InMemoryVendorRepository(
                $redisRepo, 
                (int) Config::get('VENDOR_CACHE_LIMIT', 500)
            );
        });

        $container->set(\Magma\models\SiteReviewRepositoryInterface::class, function ($c) {
            return new SiteReviewRepository($c->get('db.write'), $c->get('db.read'));
        });

        $container->set(UserRepositoryInterface::class, function ($c) {
            return new UserRepository($c->get('db.write'), $c->get('db.read'));
        });

        $container->set(\Magma\models\UserTokenRepositoryInterface::class, function ($c) {
            return new UserTokenRepository($c->get('db.write'), $c->get('db.read'));
        });



        $container->set(TransactionManagerInterface::class, function ($c) {
            return new DatabaseTransactionManager($c->get('db.write'));
        });
    }
}
