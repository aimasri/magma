<?php

declare(strict_types=1);

namespace Magma\database;

use Magma\interfaces\CacheInterface;

/**
 * Title: Cached Repository Decorator Base
 *
 * Purpose:
 * - Intercept read calls to underlying repositories to supply cached values.
 * - Standardize the Cache-Aside pattern with automated TTL resolution and driver-agnostic serialization.
 *
 * Why / Why this design:
 * - Decorator Pattern: Enhances repository reads transparently without mutating underlying SQL persistence logic.
 * - Single Responsibility Principle (SRP) & Dependency Inversion: Decouples caching logic from database queries 
 *   and abstracts physical cache driver dependencies behind `CacheInterface`.
 *
 * Teaching notes:
 * - Ideal for low-frequency mutation dictionaries (e.g. system tax rates, unit conversion matrices, taxonomies).
 * - `remember()` handles cache-hit retrieval, cache-miss fallback invocation, and storage coordination in one call.
 */
abstract class CachedRepositoryDecorator
{
    /**
     * Cache driver instance.
     */
    protected CacheInterface $cache;

    /**
     * Default Time-To-Live duration in seconds.
     */
    protected int $defaultTtl;

    /**
     * Initializes the Cached Repository Decorator.
     *
     * @param CacheInterface $cache Standardized cache driver.
     * @param int $defaultTtl Default expiration time in seconds (default 3600).
     */
    public function __construct(CacheInterface $cache, int $defaultTtl = 3600)
    {
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Retrieves a cached value or executes the fallback callback on a cache miss.
     *
     * Execution Flow:
     * 1. Attempt to fetch value from cache using $key.
     * 2. If item is cached and not null, return it immediately.
     * 3. On cache miss, execute the $callback closure to fetch fresh data from repository.
     * 4. Persist the fresh data in cache with specified or default TTL.
     * 5. Return the fresh result.
     *
     * Logic behind the logic:
     * - The "Cache-Aside" pattern defers cache population until explicitly requested, 
     *   ensuring minimal cache memory waste for untouched records.
     *
     * @param string $key Unique cache key.
     * @param int|null $ttl Expiration TTL in seconds (or null to use default).
     * @param callable $callback Fallback closure returning data on cache miss.
     * @return mixed
     */
    protected function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $lockKey = $key . ':lock';
        $acquired = $this->cache->add($lockKey, true, 10); // 10-second lease

        if (!$acquired) {
            $attempts = 0;
            while ($attempts < 50) { // Max 5 seconds waiting
                usleep(100000); // 100ms polling
                $cached = $this->cache->get($key);
                if ($cached !== null) {
                    return $cached;
                }
                $attempts++;
            }
        }

        try {
            $result = $callback();
            $this->cache->set($key, $result, $ttl ?? $this->defaultTtl);
            return $result;
        } finally {
            if ($acquired) {
                $this->cache->delete($lockKey);
            }
        }
    }

    /**
     * Eagerly invalidates a specific cache key.
     *
     * @param string $key Cache key to purge.
     * @return bool True if successfully deleted.
     */
    protected function invalidate(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * Eagerly invalidates a list of cache keys.
     *
     * @param iterable<string> $keys List of cache keys to purge.
     * @return bool True on success.
     */
    protected function invalidateMultiple(iterable $keys): bool
    {
        return $this->cache->deleteMultiple($keys);
    }
}
