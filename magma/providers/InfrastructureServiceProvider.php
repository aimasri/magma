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
use Magma\infrastructure\storage\S3StorageService;

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
            $driver = Config::get('STORAGE_DRIVER', 'local');
            if ($driver === 's3') {
                $bucket = Config::get('AWS_BUCKET', 'magma-uploads');
                $region = Config::get('AWS_DEFAULT_REGION', 'us-east-1');
                $key = Config::get('AWS_ACCESS_KEY_ID', '');
                $secret = Config::get('AWS_SECRET_ACCESS_KEY', '');
                $endpoint = Config::get('AWS_ENDPOINT');
                $url = Config::get('AWS_URL');
                
                return new S3StorageService(
                    is_scalar($bucket) ? (string)$bucket : 'magma-uploads',
                    is_scalar($region) ? (string)$region : 'us-east-1',
                    is_scalar($key) ? (string)$key : '',
                    is_scalar($secret) ? (string)$secret : '',
                    is_scalar($endpoint) ? (string)$endpoint : null,
                    is_scalar($url) ? (string)$url : null
                );
            }
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
                    throw new \RuntimeException('Redis connection failed.');
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
                throw new \RuntimeException('Redis configuration or connection error: ' . $e->getMessage(), 0, $e);
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

        $container->set(QueueInterface::class, function ($c) {
            return new RedisQueue($c->get(\Redis::class));
        });
    }
}
