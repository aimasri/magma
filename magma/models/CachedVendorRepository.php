<?php

namespace Magma\models;

/**
 * Cached Vendor Repository Decorator
 *
 * Purpose:
 * - Wraps a base VendorRepositoryInterface implementation to add Redis caching
 *   capabilities without modifying the underlying database logic.
 *
 * Why / Why this design:
 * - Implements the Decorator Pattern to satisfy the Single Responsibility Principle.
 *   This class only concerns itself with caching; the wrapped repository only 
 *   concerns itself with data retrieval.
 *
 * Teaching notes:
 * - Notice how this class implements the exact same interface as the wrapped object. 
 *   This allows the DI container to swap the plain database repository for this cached 
 *   version completely invisibly to the rest of the application.
 */
class CachedVendorRepository implements VendorRepositoryInterface
{
    private VendorRepositoryInterface $repository;
    private ?\Redis $redis;
    private int $primaryVendorId;

    public function __construct(
        VendorRepositoryInterface $repository,
        ?\Redis $redis = null,
        int $primaryVendorId = 1
    ) {
        $this->repository = $repository;
        $this->redis = $redis;
        $this->primaryVendorId = $primaryVendorId;
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
        return $this->repository->find($id);
    }

    public function update(int $id, array $data): bool
    {
        $success = $this->repository->update($id, $data);

        if ($success && $this->redis) {
            $this->redis->del("vendor:{$id}");
        }

        return $success;
    }

    public function delete(int $id): bool
    {
        $success = $this->repository->delete($id);

        if ($success && $this->redis) {
            $this->redis->del("vendor:{$id}");
        }

        return $success;
    }

    public function getPrimaryVendor(): ?array
    {
        $cacheKey = "vendor:{$this->primaryVendorId}";

        if ($this->redis) {
            $cached = $this->redis->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }

        $vendor = $this->repository->getPrimaryVendor();

        if ($this->redis && $vendor) {
            // Cache for 24 hours (86400 seconds) since we proactively invalidate on update
            $this->redis->setex($cacheKey, 86400, json_encode($vendor));
        }

        return $vendor;
    }
}
