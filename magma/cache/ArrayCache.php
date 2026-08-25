<?php

declare(strict_types=1);

namespace Magma\cache;

use DateInterval;
use DateTime;
use Magma\interfaces\CacheInterface;

/**
 * Title: In-Memory Array Cache Driver
 *
 * Purpose:
 * - Provide an ephemeral in-memory cache implementation of `CacheInterface` for unit testing and CLI operations.
 * - Store serialized or raw PHP values in memory with precise TTL expiration enforcement.
 *
 * Why / Why this design:
 * - Eliminates external service dependencies (Redis/Memcached) during local automated testing or zero-cache environments.
 * - Adheres to the Liskov Substitution Principle (LSP), allowing seamless substitution for `RedisCache`.
 *
 * Teaching notes:
 * - Data stored in this driver is lost as soon as the PHP execution process terminates.
 * - TTL checks are performed lazily upon `get()` or `has()` invocation.
 */
class ArrayCache implements CacheInterface
{
    /**
     * In-memory storage dictionary: [key => ['value' => mixed, 'expires_at' => ?float]]
     * @var array<string, array{value: mixed, expires_at: ?float}>
     */
    private array $storage = [];

    /**
     * Fetches a value from the in-memory cache.
     *
     * Execution Flow:
     * 1. Check if the key exists in $storage.
     * 2. If exists, check whether an expiration timestamp is set and if it has elapsed against microtime(true).
     * 3. If expired, remove the entry from memory and return $default.
     * 4. Otherwise, return the cached value.
     *
     * Logic behind the logic:
     * - Lazy expiration cleanup prevents memory accumulation without requiring active background sweeping.
     *
     * @param string $key Cache key.
     * @param mixed $default Fallback value.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $entry = $this->storage[$key];
        if ($entry['expires_at'] !== null && microtime(true) > $entry['expires_at']) {
            unset($this->storage[$key]);
            return $default;
        }

        return $entry['value'];
    }

    /**
     * Persists an item in memory with optional TTL.
     *
     * Execution Flow:
     * 1. Resolve expiration timestamp from TTL parameter (integer seconds, DateInterval, or null).
     * 2. Store entry in internal $storage array.
     * 3. Return true.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @param null|int|DateInterval $ttl Expiration duration.
     * @return bool
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = $this->calculateExpiry($ttl);
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
        return true;
    }

    public function add(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }
        return $this->set($key, $value, $ttl);
    }

    /**
     * Deletes an item from the in-memory cache.
     *
     * Execution Flow:
     * 1. Unset the key from $storage if present.
     * 2. Return true.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    /**
     * Clears all items from the in-memory cache.
     *
     * Execution Flow:
     * 1. Reset $storage to an empty array.
     * 2. Return true.
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    /**
     * Obtains multiple cache items by their keys.
     *
     * Execution Flow:
     * 1. Iterate over keys and fetch each item via get().
     * 2. Return associative array of [key => value].
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Persists multiple key => value items with an optional TTL.
     *
     * Execution Flow:
     * 1. Calculate expiration timestamp.
     * 2. Iterate through values and store each in $storage.
     * 3. Return true.
     *
     * @param iterable<string, mixed> $values
     * @param null|int|DateInterval $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = $this->calculateExpiry($ttl);
        foreach ($values as $key => $value) {
            $this->storage[(string) $key] = [
                'value' => $value,
                'expires_at' => $expiresAt,
            ];
        }
        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * Execution Flow:
     * 1. Iterate over keys and remove each from $storage.
     * 2. Return true.
     *
     * @param iterable<string> $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->storage[$key]);
        }
        return true;
    }

    /**
     * Determines whether an item is present in the cache and unexpired.
     *
     * Execution Flow:
     * 1. Check if key exists in $storage.
     * 2. Check if expired. If expired, remove and return false.
     * 3. Return true if valid.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        if ($this->storage[$key]['expires_at'] !== null && microtime(true) > $this->storage[$key]['expires_at']) {
            unset($this->storage[$key]);
            return false;
        }

        return true;
    }

    /**
     * Converts a TTL specification into a Unix timestamp in seconds with microsecond precision.
     *
     * @param null|int|DateInterval $ttl
     * @return float|null
     */
    private function calculateExpiry(null|int|DateInterval $ttl): ?float
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $then = (clone $now)->add($ttl);
            return (float) $then->getTimestamp();
        }

        if ($ttl <= 0) {
            return microtime(true) - 1.0;
        }

        return microtime(true) + (float) $ttl;
    }
}
