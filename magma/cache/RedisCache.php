<?php

declare(strict_types=1);

namespace Magma\cache;

use DateInterval;
use DateTime;
use Redis;
use Throwable;
use __PHP_Incomplete_Class;
use Magma\interfaces\CacheInterface;

/**
 * Title: Redis Cache Adapter with Resilient Deserialization Fallback
 *
 * Purpose:
 * - Implement `CacheInterface` backed by a native PHP `Redis` connection.
 * - Provide atomic Redis caching, namespace key prefixing, and TTL expiration management.
 * - Guarantee zero fatal crashes upon encountering corrupted, malformed, or stale serialized payloads.
 *
 * Why / Why this design:
 * - In distributed architectures and staging-to-production deploys, serialized PHP objects in Redis may 
 *   become invalid if class properties, namespaces, or class names change. 
 * - Uncaught `unserialize()` errors or `__PHP_Incomplete_Class` instances cause fatal HTTP 500 errors. 
 *   This adapter catches deserialization anomalies, eagerly evicts the corrupted key from Redis, and 
 *   gracefully returns a cache miss so the application transparently re-queries the database.
 *
 * Teaching notes:
 * - Keys are automatically scoped by a namespace prefix (default `magma:cache:`).
 * - Implements PSR-16 Simple Cache semantics.
 */
class RedisCache implements CacheInterface
{
    /**
     * Active Redis connection.
     */
    private Redis $redis;

    private \Magma\logging\LoggerInterface $logger;

    /**
     * Cache key namespace prefix.
     */
    private string $prefix;

    /**
     * Default Time-To-Live duration in seconds.
     */
    private int $defaultTtl;

    /**
     * Initializes the Redis Cache driver.
     *
     * @param Redis $redis Configured Redis connection.
     * @param \Magma\logging\LoggerInterface $logger System logger.
     * @param string $prefix Namespace key prefix (default 'magma:cache:').
     * @param int $defaultTtl Default TTL in seconds (default 3600).
     */
    public function __construct(Redis $redis, \Magma\logging\LoggerInterface $logger, string $prefix = 'magma:cache:', int $defaultTtl = 3600)
    {
        $this->redis = $redis;
        $this->logger = $logger;
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Fetches an entry from Redis with resilient deserialization fallback.
     *
     * Execution Flow:
     * 1. Prefix key and fetch raw payload from Redis.
     * 2. If false (key missing or expired), return $default.
     * 3. Attempt to unserialize the raw payload inside a try-catch block.
     * 4. If deserialization throws, returns false (when not boolean false), or yields __PHP_Incomplete_Class:
     *    a. Evict the corrupt key from Redis via delete().
     *    b. Return $default as a graceful cache miss.
     * 5. Return the deserialized value.
     *
     * @param string $key Unique cache key.
     * @param mixed $default Value returned on cache miss or corrupted payload.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $prefixedKey = $this->prefixKey($key);

        try {
            $raw = $this->redis->get($prefixedKey);
            if ($raw === false || !is_string($raw)) {
                return $default;
            }

            $unserialized = @unserialize($raw);

            // Guard against corrupted payload or incomplete class definitions
            if ($unserialized === false && $raw !== serialize(false)) {
                $this->delete($key);
                return $default;
            }

            if ($unserialized instanceof __PHP_Incomplete_Class) {
                $this->delete($key);
                return $default;
            }

            return $unserialized;
        } catch (Throwable $e) {
            $this->logger->error('Redis Cache get failure', ['exception' => $e->getMessage(), 'key' => $key]);
            $this->delete($key);
            return $default;
        }
    }

    /**
     * Persists an item in Redis with serialization and optional TTL.
     *
     * Execution Flow:
     * 1. Calculate TTL in seconds.
     * 2. If TTL <= 0, delete key and return true.
     * 3. Serialize value to string.
     * 4. Call setex() or set() on Redis connection.
     * 5. Return boolean result.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @param null|int|DateInterval $ttl Expiration duration.
     * @return bool
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $seconds = $this->resolveTtl($ttl);

        if ($seconds <= 0) {
            return $this->delete($key);
        }

        $serialized = serialize($value);

        try {
            return (bool) $this->redis->setex($prefixedKey, $seconds, $serialized);
        } catch (Throwable $e) {
            $this->logger->error('Redis Cache set failure', ['exception' => $e->getMessage(), 'key' => $key]);
            return false;
        }
    }

    public function add(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $seconds = $this->resolveTtl($ttl);

        if ($seconds <= 0) {
            return false;
        }

        $serialized = serialize($value);

        try {
            return (bool) $this->redis->set($prefixedKey, $serialized, ['nx', 'ex' => $seconds]);
        } catch (Throwable $e) {
            $this->logger->error('Redis Cache add failure', ['exception' => $e->getMessage(), 'key' => $key]);
            return false;
        }
    }

    /**
     * Deletes an item from Redis.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            $this->redis->del($this->prefixKey($key));
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Redis Cache delete failure', ['exception' => $e->getMessage(), 'key' => $key]);
            return false;
        }
    }

    /**
     * Wipes all keys belonging to this cache prefix.
     *
     * Execution Flow:
     * 1. Search keys matching prefix wildcard (`prefix*`).
     * 2. Delete found keys in batch.
     * 3. Return true.
     *
     * @return bool
     */
    public function clear(): bool
    {
        try {
            $pattern = $this->prefix . '*';
            $keys = $this->redis->keys($pattern);
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            return true;
        } catch (Throwable $e) {
            $this->logger->warning('Redis Cache clear failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtains multiple cache items by their keys.
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get((string) $key, $default);
        }
        return $results;
    }

    /**
     * Persists multiple key-value pairs with an optional TTL.
     *
     * @param iterable<string, mixed> $values
     * @param null|int|DateInterval $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Deletes multiple items from Redis in a single operation.
     *
     * @param iterable<string> $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefixKey((string) $key);
        }

        if (empty($prefixedKeys)) {
            return true;
        }

        try {
            $this->redis->del($prefixedKeys);
            return true;
        } catch (Throwable $e) {
            $this->logger->warning('Redis Cache deleteMultiple failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Determines whether an item exists in Redis.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has(string $key): bool
    {
        try {
            return (bool) $this->redis->exists($this->prefixKey($key));
        } catch (Throwable $e) {
            $this->logger->warning('Redis Cache has failed', ['exception' => $e->getMessage(), 'key' => $key]);
            return false;
        }
    }

    /**
     * Prefixes a raw cache key with the configured namespace.
     *
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Resolves TTL input into an integer seconds value.
     *
     * @param null|int|DateInterval $ttl
     * @return int
     */
    private function resolveTtl(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return $this->defaultTtl;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $then = (clone $now)->add($ttl);
            return max(0, $then->getTimestamp() - $now->getTimestamp());
        }

        return (int) $ttl;
    }
}
