<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\database\TransactionManagerInterface;
use Magma\database\DatabaseTransactionManager;

use Magma\models\VendorCommandRepository;
use Magma\models\VendorCommandInterface;
use Magma\models\VendorQueryRepository;
use Magma\models\VendorQueryInterface;
use Magma\models\CachedVendorQueryRepository;
use Magma\models\SiteReviewRepository;
use Magma\models\UserRepository;
use Magma\models\UserRepositoryInterface;
use Magma\models\RememberTokenRepository;
use Magma\models\PasswordResetTokenRepository;


/**
 * RepositoryServiceProvider — registers all database repository abstractions.
 */
class RepositoryServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(VendorCommandInterface::class, function ($c) {
            return new VendorCommandRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                new \Magma\models\VendorMapper()
            );
        });

        $container->set(VendorQueryInterface::class, function ($c) {
            $baseRepo = new VendorQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                new \Magma\models\VendorMapper(),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            $redisRepo = new CachedVendorQueryRepository(
                $baseRepo,
                $c->get(\Redis::class),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            return new \Magma\models\InMemoryVendorQueryRepository(
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

        $container->set(RememberTokenRepository::class, function ($c) {
            return new RememberTokenRepository($c->get('db.write'), $c->get('db.read'));
        });

        $container->set(PasswordResetTokenRepository::class, function ($c) {
            return new PasswordResetTokenRepository($c->get('db.write'), $c->get('db.read'));
        });

        $container->set(TransactionManagerInterface::class, function ($c) {
            return new DatabaseTransactionManager($c->get('db.write'));
        });
    }
}
