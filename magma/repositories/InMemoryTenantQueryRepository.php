<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\TenantQueryInterface;

use Magma\dto\TenantDTO;

/**
 * Title: In-Memory Tenant Query Repository
 * Purpose:
 * - Provides an in-memory caching layer for tenant queries during a single request lifecycle.
 * - Decorates the base tenant query repository to prevent redundant database calls within the same process.
 * - Handles cache size limitation to prevent memory leaks.
 * Why/Why this design:
 * - Uses the Decorator pattern to inject request-level caching transparently.
 * - Ensures that multiple calls for the same tenant data in a single request do not hit the database or external cache (Redis) repeatedly.
 * Teaching notes:
 * - This acts as a Level 1 (L1) cache. It's an industry standard to combine this with an L2 cache (like Redis) for optimal performance.
 * - The cache limit is a crucial safeguard in long-running processes (like workers) to avoid exhausting PHP memory.
 */
class InMemoryTenantQueryRepository implements TenantQueryInterface
{
    private TenantQueryInterface $repository;
    /** @var array<int|string, TenantDTO|null> */
    private array $cache = [];
    private ?int $primaryTenantId = null;
    private int $cacheLimit;

    /**
     * Initializes the in-memory tenant query repository decorator.
     *
     * Logic behind the logic:
     * - Wraps an existing TenantQueryInterface implementation to provide a fast L1 cache.
     * - The cache limit prevents out-of-memory errors in long-running processes like queue workers.
     *
     * @param TenantQueryInterface $repository The base repository to decorate.
     * @param int $cacheLimit Maximum number of entities to store in memory.
     */
    public function __construct(TenantQueryInterface $repository, int $cacheLimit = 500)
    {
        $this->repository = $repository;
        $this->cacheLimit = $cacheLimit;
    }

    /**
     * @return iterable<int, TenantDTO>
     */
    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    /**
     * Finds a tenant by ID using the in-memory cache.
     * 
     * 1. Checks if the tenant ID exists in the local array cache.
     * 2. Returns the cached tenant immediately if found.
     * 3. Fetches the tenant from the underlying repository if not found.
     * 4. Stores the fetched tenant in the local cache and enforces the cache memory limit.
     * 5. Returns the fetched tenant.
     * 
     * Logic behind the logic: This request-level caching strategy ensures we don't query the database multiple times for the same entity within a single execution cycle, which is a common issue in complex domain logic.
     */
    public function find(int $id): ?TenantDTO
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        $tenant = $this->repository->find($id);
        
        $this->cache[$id] = $tenant;
        $this->enforceCacheLimit();
        
        return $tenant;
    }

    /**
     * Retrieves the primary tenant, caching it in memory.
     * 
     * 1. Checks if the primary tenant is already cached under the 'primary' key.
     * 2. Fetches the primary tenant from the base repository if not cached.
     * 3. Caches the tenant using both the 'primary' key and its actual ID key.
     * 4. Enforces the cache limit to prevent memory bloat.
     * 
     * Logic behind the logic: Caching under both 'primary' and the specific ID ensures that subsequent calls to `find()` for the primary tenant's ID also benefit from the cache hit.
     */
    public function getPrimaryTenant(): ?TenantDTO
    {
        if (array_key_exists('primary', $this->cache)) {
            return $this->cache['primary'];
        }

        $tenant = $this->repository->getPrimaryTenant();
        $this->cache['primary'] = $tenant;
        
        if ($tenant) {
            $this->primaryTenantId = $tenant->id;
            $this->cache[$this->primaryTenantId] = $tenant;
        }
        
        $this->enforceCacheLimit();
        
        return $tenant;
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
