<?php

declare(strict_types=1);

namespace Magma\interfaces;

use DateInterval;

/**
 * Title: Standardized Cache Interface (PSR-16 Compatible)
 *
 * Purpose:
 * - Define a standardized cache contract across all Magma framework drivers (Redis, In-Memory Array, File).
 * - Standardize cache interactions (`get`, `set`, `delete`, `clear`, `getMultiple`, `setMultiple`, `deleteMultiple`, `has`).
 * - Decouple application repositories and domain services from concrete caching infrastructure.
 *
 * Why / Why this design:
 * - Adheres strictly to the Dependency Inversion Principle (DIP).
 * - Enables seamless cache driver swapping between CLI/testing environments (ArrayCache) and 
 *   production multi-tenant clusters (RedisCache).
 * - Standardizing TTL resolution (seconds or DateInterval) guarantees predictable expiration behavior.
 *
 * Teaching notes:
 * - All caching implementations must return boolean success flags on mutations and safe defaults on misses.
 */
interface CacheInterface
{
    /**
     * Fetches an entry from the cache by its unique key.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The cached value, or $default on a cache miss.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists an item in the cache with an optional Time-To-Live (TTL).
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store. Must be serializable.
     * @param null|int|DateInterval $ttl Optional. The TTL value (in seconds or DateInterval).
     * @return bool True on success, false on failure.
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool;

    /**
     * Persists an item in the cache only if it does not already exist.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @param null|int|DateInterval $ttl Optional TTL.
     * @return bool True if stored, false if it already existed.
     */
    public function add(string $key, mixed $value, null|int|DateInterval $ttl = null): bool;

    /**
     * Deletes an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed or didn't exist, false on failure.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache namespace or store.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys A list of keys to fetch in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     * @return iterable<string, mixed> Key-value pairs of cached data.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists multiple key => value items in the cache with an optional TTL.
     *
     * @param iterable<string, mixed> $values Key => value pairs to persist.
     * @param null|int|DateInterval $ttl Optional. Expiration TTL for the items.
     * @return bool True on success, false on failure.
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     * @return bool True if all items were successfully removed, false on failure.
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key.
     * @return bool True if key exists and has not expired, false otherwise.
     */
    public function has(string $key): bool;
}
