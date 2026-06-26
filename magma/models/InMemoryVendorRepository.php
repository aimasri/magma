<?php

namespace Magma\models;


/**
 * In-Memory Vendor Repository Decorator
 *
 * Purpose:
 * - Wraps a base VendorRepositoryInterface implementation to add L1 (in-process memory) 
 *   caching capabilities without modifying the underlying logic or L2 (Redis) cache.
 *
 * Why / Why this design:
 * - Implements the Decorator Pattern to satisfy the Single Responsibility Principle.
 *   This class only concerns itself with in-memory memoization.
 *
 * Teaching notes:
 * - This provides instantaneous (0ms) data retrieval for vendors that have already 
 *   been fetched during the current HTTP request lifecycle. By stacking this on top 
 *   of the Redis decorator, we strictly adhere to the SRP.
 */
class InMemoryVendorRepository implements VendorRepositoryInterface
{
    private VendorRepositoryInterface $repository;
    private array $cache = [];
    private ?int $primaryVendorId = null;
    private int $cacheLimit;

    public function __construct(VendorRepositoryInterface $repository, int $cacheLimit = 500)
    {
        $this->repository = $repository;
        $this->cacheLimit = $cacheLimit;
    }

    public function create(array $data): bool
    {
        return $this->repository->create($data);
    }

    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    public function find(int $id): ?array
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        $vendor = $this->repository->find($id);
        
        $this->cache[$id] = $vendor;
        $this->enforceCacheLimit();
        
        return $vendor;
    }

    public function update(int $id, array $data): bool
    {
        $success = $this->repository->update($id, $data);
        if ($success) {
            unset($this->cache[$id]);
            if ($this->primaryVendorId === $id) {
                unset($this->cache['primary']);
            }
        }
        return $success;
    }

    public function delete(int $id): bool
    {
        $success = $this->repository->delete($id);
        if ($success) {
            unset($this->cache[$id]);
            if ($this->primaryVendorId === $id) {
                unset($this->cache['primary']);
            }
        }
        return $success;
    }

    public function getPrimaryVendor(): ?array
    {
        if (array_key_exists('primary', $this->cache)) {
            return $this->cache['primary'];
        }

        $vendor = $this->repository->getPrimaryVendor();
        $this->cache['primary'] = $vendor;
        
        if ($vendor && isset($vendor['id'])) {
            $this->primaryVendorId = (int)$vendor['id'];
            $this->cache[$this->primaryVendorId] = $vendor;
        }
        
        $this->enforceCacheLimit();
        
        return $vendor;
    }

    /**
     * Enforces the maximum memory size of the cache array.
     * 
     * Purpose:
     * - Prevents the $cache array from growing infinitely in long-running processes (like queue workers).
     * - Uses a basic FIFO (First-In-First-Out) eviction strategy.
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
