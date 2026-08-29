<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\database\TransactionManagerInterface;
use Magma\database\DatabaseTransactionManager;

use Magma\repositories\TenantCommandRepository;
use Magma\interfaces\cqrs\TenantCommandInterface;
use Magma\repositories\TenantQueryRepository;
use Magma\interfaces\cqrs\TenantQueryInterface;
use Magma\repositories\CachedTenantQueryRepository;
use Modules\Reviews\repositories\SiteReviewQueryRepository;
use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Modules\Reviews\repositories\SiteReviewCommandRepository;
use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Magma\repositories\UserQueryRepository;
use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\repositories\UserCommandRepository;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\repositories\RememberTokenRepository;
use Magma\repositories\PasswordResetTokenRepository;


/**
 * Title: Repository Service Provider
 *
 * Purpose:
 * - Register all CQRS database repository abstractions into the DI Container.
 * - Wire up dependencies for data access including tenant context, connections, and cache layers.
 *
 * Why / Why this design:
 * - Centralizes the binding of concrete repositories to their interfaces (e.g. `TenantQueryInterface` -> `TenantQueryRepository`).
 * - Encapsulates complex instantiation logic (such as wrapping a base repository in a `CachedTenantQueryRepository`).
 *
 * Teaching notes:
 * - Decorator Pattern is heavily used here. For instance, `TenantQueryInterface` binds to an `InMemoryTenantQueryRepository`, which decorates a `CachedTenantQueryRepository` (Redis), which ultimately decorates the base PostgreSQL `TenantQueryRepository`.
 */
class RepositoryServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers repository bindings into the container.
     * 
     * @param Container $container
     */
    public function register(Container $container): void
    {
        $container->set(TenantCommandInterface::class, function ($c) {
            return new TenantCommandRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(TenantQueryInterface::class, function ($c) {
            $primaryCfg = Config::get('PRIMARY_TENANT_ID', 1);
            $primaryId = is_scalar($primaryCfg) ? (int)$primaryCfg : 1;
            
            $baseRepo = new TenantQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                new \Magma\repositories\TenantMapper(),
                $primaryId
            );
            $redisRepo = new CachedTenantQueryRepository(
                $baseRepo,
                $c->get(\Redis::class),
                $primaryId
            );
            
            $limitCfg = Config::get('TENANT_CACHE_LIMIT', 500);
            $limit = is_scalar($limitCfg) ? (int)$limitCfg : 500;
            
            return new \Magma\repositories\InMemoryTenantQueryRepository(
                $redisRepo, 
                $limit
            );
        });

        $container->set(\Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface::class, function ($c) {
            return new SiteReviewQueryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });
        
        $container->set(\Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface::class, function ($c) {
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
                $c->get(\Magma\security\TenantContext::class),
                $c->get(\Magma\contracts\ClockInterface::class)
            );
        });

        $container->set(\Magma\interfaces\repositories\RememberTokenRepositoryInterface::class, function ($c) {
            return new RememberTokenRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                $c->get(\Magma\contracts\ClockInterface::class)
            );
        });

        $container->set(\Magma\interfaces\repositories\PasswordResetTokenRepositoryInterface::class, function ($c) {
            return new PasswordResetTokenRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class),
                $c->get(\Magma\contracts\ClockInterface::class)
            );
        });

        $container->set(\Magma\database\OutboxJobRepositoryInterface::class, function ($c) {
            return new \Magma\database\OutboxJobRepository($c->get(\Magma\database\DatabaseConnectionManager::class));
        });

        $container->set(\Magma\database\OutboxJobRepository::class, function ($c) {
            return new \Magma\database\OutboxJobRepository($c->get(\Magma\database\DatabaseConnectionManager::class));
        });

        $container->set(TransactionManagerInterface::class, function ($c) {
            return new DatabaseTransactionManager($c->get(\Magma\database\DatabaseConnectionManager::class));
        });
    }
}
