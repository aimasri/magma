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
            return new LocalStorageService($storagePath, $storageUrl);
        });

        $container->set(StorageInterface::class, function (Container $c) {
            $driver = Config::get('STORAGE_DRIVER', 'local');
            if ($driver === 's3') {
                return new S3StorageService(
                    (string)Config::get('AWS_BUCKET', 'magma-uploads'),
                    (string)Config::get('AWS_DEFAULT_REGION', 'us-east-1'),
                    (string)Config::get('AWS_ACCESS_KEY_ID', ''),
                    (string)Config::get('AWS_SECRET_ACCESS_KEY', ''),
                    Config::get('AWS_ENDPOINT'),
                    Config::get('AWS_URL')
                );
            }
            return $c->get(LocalStorageService::class);
        });

        $container->set(\Magma\interfaces\StorageInterface::class, function (Container $c) {
            return $c->get(StorageInterface::class);
        });

        $container->set(\Redis::class, function () {
            $redis = new \Redis();

            try {
                $timeout = (float)Config::get('REDIS_TIMEOUT', 2.0);
                $connected = $redis->connect(
                    Config::get('REDIS_HOST', '127.0.0.1'),
                    (int)Config::get('REDIS_PORT', 6379),
                    $timeout
                );

                if (!$connected) {
                    throw new \RuntimeException('Redis connection failed.');
                }

                $password = Config::get('REDIS_PASSWORD');
                if ($password !== null) {
                    $redis->auth($password);
                }

                $db = Config::get('REDIS_DB');
                if ($db !== null) {
                    $redis->select((int)$db);
                }
            } catch (\RedisException $e) {
                throw new \RuntimeException('Redis configuration or connection error: ' . $e->getMessage(), 0, $e);
            }

            return $redis;
        });

        $container->set(CacheInterface::class, function ($c) {
            try {
                $redis = $c->get(\Redis::class);
                return new RedisCache($redis, 'magma:cache:', (int)Config::get('CACHE_TTL', 3600));
            } catch (\Throwable) {
                return new ArrayCache();
            }
        });

        $container->set(\Magma\services\ImageProcessingService::class, function (Container $c) {
            return new \Magma\services\ImageProcessingService($c->get(StorageInterface::class));
        });

        $container->set(RateLimiterInterface::class, function ($c) {
            return new RedisRateLimiter($c->get(\Redis::class));
        });

        $container->set(QueueInterface::class, function ($c) {
            return new RedisQueue($c->get(\Redis::class));
        });
    }
}
