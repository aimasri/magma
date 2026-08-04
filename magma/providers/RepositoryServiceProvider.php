<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\database\TransactionManagerInterface;
use Magma\database\DatabaseTransactionManager;

use Magma\repositories\VendorCommandRepository;
use Magma\interfaces\cqrs\VendorCommandInterface;
use Magma\repositories\VendorQueryRepository;
use Magma\interfaces\cqrs\VendorQueryInterface;
use Magma\repositories\CachedVendorQueryRepository;
use Magma\repositories\SiteReviewQueryRepository;
use Magma\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\repositories\SiteReviewCommandRepository;
use Magma\interfaces\cqrs\SiteReviewCommandInterface;
use Magma\repositories\UserQueryRepository;
use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\repositories\UserCommandRepository;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\repositories\RememberTokenRepository;
use Magma\repositories\PasswordResetTokenRepository;


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
                new \Magma\repositories\VendorMapper()
            );
        });

        $container->set(VendorQueryInterface::class, function ($c) {
            $baseRepo = new VendorQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                new \Magma\repositories\VendorMapper(),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            $redisRepo = new CachedVendorQueryRepository(
                $baseRepo,
                $c->get(\Redis::class),
                Config::get('PRIMARY_VENDOR_ID', 1)
            );
            return new \Magma\repositories\InMemoryVendorQueryRepository(
                $redisRepo, 
                (int) Config::get('VENDOR_CACHE_LIMIT', 500)
            );
        });

        $container->set(\Magma\interfaces\cqrs\SiteReviewQueryInterface::class, function ($c) {
            return new SiteReviewQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });
        
        $container->set(\Magma\interfaces\cqrs\SiteReviewCommandInterface::class, function ($c) {
            return new SiteReviewCommandRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(UserQueryInterface::class, function ($c) {
            return new UserQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });
        
        $container->set(UserCommandInterface::class, function ($c) {
            return new UserCommandRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(RememberTokenRepository::class, function ($c) {
            return new RememberTokenRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(PasswordResetTokenRepository::class, function ($c) {
            return new PasswordResetTokenRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(TransactionManagerInterface::class, function ($c) {
            return new DatabaseTransactionManager($c->get(\Magma\database\DatabaseConnectionManager::class));
        });
    }
}
