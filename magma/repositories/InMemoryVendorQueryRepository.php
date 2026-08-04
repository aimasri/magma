<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\VendorQueryInterface;

use Magma\dto\VendorDTO;

/**
 * Title: In-Memory Vendor Query Repository
 * Purpose:
 * - Provides an in-memory caching layer for vendor queries during a single request lifecycle.
 * - Decorates the base vendor query repository to prevent redundant database calls within the same process.
 * - Handles cache size limitation to prevent memory leaks.
 * Why/Why this design:
 * - Uses the Decorator pattern to inject request-level caching transparently.
 * - Ensures that multiple calls for the same vendor data in a single request do not hit the database or external cache (Redis) repeatedly.
 * Teaching notes:
 * - This acts as a Level 1 (L1) cache. It's an industry standard to combine this with an L2 cache (like Redis) for optimal performance.
 * - The cache limit is a crucial safeguard in long-running processes (like workers) to avoid exhausting PHP memory.
 */
class InMemoryVendorQueryRepository implements VendorQueryInterface
{
    private VendorQueryInterface $repository;
    private array $cache = [];
    private ?int $primaryVendorId = null;
    private int $cacheLimit;

    public function __construct(VendorQueryInterface $repository, int $cacheLimit = 500)
    {
        $this->repository = $repository;
        $this->cacheLimit = $cacheLimit;
    }

    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    /**
     * Finds a vendor by ID using the in-memory cache.
     * 
     * 1. Checks if the vendor ID exists in the local array cache.
     * 2. Returns the cached vendor immediately if found.
     * 3. Fetches the vendor from the underlying repository if not found.
     * 4. Stores the fetched vendor in the local cache and enforces the cache memory limit.
     * 5. Returns the fetched vendor.
     * 
     * Logic behind the logic: This request-level caching strategy ensures we don't query the database multiple times for the same entity within a single execution cycle, which is a common issue in complex domain logic.
     */
    public function find(int $id): ?VendorDTO
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        $vendor = $this->repository->find($id);
        
        $this->cache[$id] = $vendor;
        $this->enforceCacheLimit();
        
        return $vendor;
    }

    /**
     * Retrieves the primary vendor, caching it in memory.
     * 
     * 1. Checks if the primary vendor is already cached under the 'primary' key.
     * 2. Fetches the primary vendor from the base repository if not cached.
     * 3. Caches the vendor using both the 'primary' key and its actual ID key.
     * 4. Enforces the cache limit to prevent memory bloat.
     * 
     * Logic behind the logic: Caching under both 'primary' and the specific ID ensures that subsequent calls to `find()` for the primary vendor's ID also benefit from the cache hit.
     */
    public function getPrimaryVendor(): ?VendorDTO
    {
        if (array_key_exists('primary', $this->cache)) {
            return $this->cache['primary'];
        }

        $vendor = $this->repository->getPrimaryVendor();
        $this->cache['primary'] = $vendor;
        
        if ($vendor) {
            $this->primaryVendorId = $vendor->id;
            $this->cache[$this->primaryVendorId] = $vendor;
        }
        
        $this->enforceCacheLimit();
        
        return $vendor;
    }

    /**
     * Enforces the maximum size of the in-memory cache.
     * 
     * 1. Checks if the current cache size exceeds the predefined limit.
     * 2. Identifies the oldest entry (first key) in the cache array.
     * 3. Removes the oldest entry to free up memory.
     * 
     * Logic behind the logic: Implements a rudimentary FIFO (First-In-First-Out) cache eviction policy to protect worker processes from unbounded memory growth over time.
     */
    private function enforceCacheLimit(): void
    {
        if (count($this->cache) > $this->cacheLimit) {
            $firstKey = array_key_first($this->cache);
            if ($firstKey !== null) {
                unset($this->cache[$firstKey]);
            }
        }
    }
}
