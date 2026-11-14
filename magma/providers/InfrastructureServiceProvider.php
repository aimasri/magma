<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\security\RateLimiterInterface;
use Magma\security\RedisRateLimiter;
use Magma\queue\QueueInterface;
use Magma\queue\RedisQueue;
use Magma\interfaces\CacheInterface;
use Magma\cache\RedisCache;
use Magma\cache\ArrayCache;
use Magma\infrastructure\storage\StorageInterface;
use Magma\infrastructure\storage\LocalStorageService;

/**
 * Title: InfrastructureServiceProvider
 *
 * Purpose:
 * - Bootstraps infrastructure components like Redis, Cache, Storage, and Queue services
 * - Binds abstract interfaces (CacheInterface, StorageInterface, QueueInterface, etc.) to concrete drivers
 * - Configures Redis and registers it as a singleton in the DI container
 *
 * Why / Why this design:
 * - Service Locator/Provider Pattern: Centralizes dependency registration to decouple configuration from application logic
 * - Dependency Inversion Principle (DIP): Allows the application to depend on abstractions rather than concrete implementations
 *
 * Teaching notes:
 * - Using environment configurations (Config::get) inside the provider ensures environment-specific services without hardcoding.
 */
class InfrastructureServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers infrastructure services within the DI container.
     *
     * 1. Binds LocalStorageService and S3StorageService based on config.
     * 2. Sets up the primary Redis instance using config parameters.
     * 3. Configures Cache, ImageProcessing, RateLimiting, and Queue interfaces with concrete implementations.
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(LocalStorageService::class, function () {
            $storagePath = Config::get('STORAGE_PATH', ROOT_DIR . '/storage');
            $storageUrl = Config::get('STORAGE_URL', '/storage');
            return new LocalStorageService(
                is_string($storagePath) ? $storagePath : ROOT_DIR . '/storage', 
                is_string($storageUrl) ? $storageUrl : '/storage'
            );
        });

        $container->set(StorageInterface::class, function (Container $c) {
            $local = $c->get(LocalStorageService::class);
            assert($local instanceof StorageInterface);
            return $local;
        });


        $container->set(\Redis::class, function () {
            $redis = new \Redis();

            try {
                $timeoutCfg = Config::get('REDIS_TIMEOUT', 2.0);
                $timeout = is_scalar($timeoutCfg) ? (float)$timeoutCfg : 2.0;
                $hostCfg = Config::get('REDIS_HOST', '127.0.0.1');
                $portCfg = Config::get('REDIS_PORT', 6379);
                $connected = $redis->connect(
                    is_string($hostCfg) ? $hostCfg : '127.0.0.1',
                    is_scalar($portCfg) ? (int)$portCfg : 6379,
                    $timeout
                );

                if (!$connected) {
                    return $redis;
                }

                $password = Config::get('REDIS_PASSWORD');
                if (is_string($password)) {
                    $redis->auth($password);
                } elseif (is_array($password)) {
                    $redis->auth(array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_values($password)));
                }

                $db = Config::get('REDIS_DB');
                if ($db !== null && is_scalar($db)) {
                    $redis->select((int)$db);
                }
            } catch (\RedisException $e) {
                error_log(sprintf('[InfrastructureServiceProvider] Redis connection failed: %s', $e->getMessage()));
                // Return unconnected instance so DI resolution doesn't crash.
                // Callers (RedisCache, RateLimitMiddleware) will handle failures gracefully.
            }

            return $redis;
        });

        $container->set(CacheInterface::class, function ($c) {
            $redis = $c->get(\Redis::class);
            assert($redis instanceof \Redis);
            $logger = $c->get(\Magma\logging\LoggerInterface::class);
            assert($logger instanceof \Magma\logging\LoggerInterface);
            $ttl = Config::get('CACHE_TTL', 3600);
            return new RedisCache($redis, $logger, 'magma:cache:', is_scalar($ttl) ? (int)$ttl : 3600);
        });

        $container->set(\Magma\services\ImageProcessingService::class, function () {
            return new \Magma\services\ImageProcessingService();
        });

        $container->set(RateLimiterInterface::class, function ($c) {
            $tenantContext = $c->has(\Magma\security\TenantContext::class) 
                ? $c->get(\Magma\security\TenantContext::class) 
                : null;
            return new RedisRateLimiter(
                $c->get(\Redis::class),
                $tenantContext instanceof \Magma\security\TenantContext ? $tenantContext : null
            );
        });

        $container->set(\Magma\contracts\ClockInterface::class, function () {
            return new \Magma\infrastructure\time\SystemClock();
        });

        $container->set(\Magma\logging\CorrelationIdProviderInterface::class, function () {
            return new \Magma\logging\CorrelationIdProvider();
        });

        $container->set(\Magma\logging\LoggerInterface::class, function (Container $c) {
            $corrProvider = $c->has(\Magma\logging\CorrelationIdProviderInterface::class)
                ? $c->get(\Magma\logging\CorrelationIdProviderInterface::class)
                : null;
            return new \Magma\logging\NativeLogger(
                function () use ($c): ?\Magma\security\TenantContext {
                    try {
                        $ctx = $c->has(\Magma\security\TenantContext::class)
                            ? $c->get(\Magma\security\TenantContext::class)
                            : null;
                        return $ctx instanceof \Magma\security\TenantContext ? $ctx : null;
                    } catch (\Throwable) {
                        return null;
                    }
                },
                $corrProvider instanceof \Magma\logging\CorrelationIdProviderInterface ? $corrProvider : null
            );
        });

        $container->set(QueueInterface::class, function (Container $c) {
            $prefix = Config::getString('QUEUE_PREFIX', 'queue:');
            $redis = $c->get(\Redis::class);
            assert($redis instanceof \Redis);
            return new RedisQueue($redis, $prefix !== '' ? $prefix : 'queue:');
        });

        $container->set(\Magma\queue\ReliableQueueInterface::class, function (Container $c) {
            return $c->get(QueueInterface::class);
        });
    }
}
