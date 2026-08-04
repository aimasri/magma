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

/**
 * Title: Infrastructure Service Provider
 * Purpose: Bootstraps infrastructure components like Redis, Storage, and Queue.
 */
class InfrastructureServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(\Magma\interfaces\StorageInterface::class, function ($c) {
            return new \Magma\services\LocalFileStorageService(ROOT_DIR . '/storage');
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

        $container->set(RateLimiterInterface::class, function ($c) {
            return new RedisRateLimiter($c->get(\Redis::class));
        });

        $container->set(QueueInterface::class, function ($c) {
            return new RedisQueue($c->get(\Redis::class));
        });
    }
}
