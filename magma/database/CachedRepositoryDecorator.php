<?php

namespace Magma\database;

use CacheInterface; // Assumes a PSR-16 like or internal CacheInterface exists

/**
 * Title: Cached Repository Decorator
 *
 * Purpose:
 * - Intercepts calls to repositories to supply cached values.
 * - Standardizes the TTL cache-aside pattern across dictionary/taxonomy layers.
 *
 * Why this design:
 * - Decorator Pattern: Enhances repository reads transparently without mutating the underlying data access logic.
 * - Single Responsibility Principle (SRP): Decouples caching logic from database queries.
 *
 * Teaching notes:
 * - Use this for relatively static data (e.g., country lists, status enums) that undergo low-frequency updates.
 * - Note how `remember()` elegantly handles the fallback query if the cache is missed.
 */
abstract class CachedRepositoryDecorator
{
    protected \Redis $cache;
    protected int $ttl;

    public function __construct(\Redis $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    /**
     * Retrieves a cached value or executes the callback on a cache miss.
     * 
     * 1. Checks the cache for the given key.
     * 2. If found, unserializes and returns the value.
     * 3. If missed, invokes the callable `$callback` to retrieve fresh data.
     * 4. Serializes and stores the new data with the configured TTL.
     * 
     * Logic behind the logic:
     * - The "cache-aside" pattern defers caching until data is explicitly requested. Serialization is safely used to store arrays or objects in Redis.
     *
     * @param string $key The cache key.
     * @param callable $callback The fallback function to fetch data if cache misses.
     * @return mixed
     */
    protected function remember(string $key, callable $callback): mixed
    {
        $cached = $this->cache->get($key);
        if ($cached !== false) {
            return json_decode($cached, true);
        }

        $result = $callback();
        $this->cache->setex($key, $this->ttl, json_encode($result));

        return $result;
    }

    /**
     * Invalidates a specific cache key.
     * 
     * Logic behind the logic:
     * - Eager invalidation ensures stale entries are purged instantly when source data is mutated, maintaining cache consistency.
     *
     * @param string $key
     */
    protected function invalidate(string $key): void
    {
        $this->cache->del($key);
    }
}
