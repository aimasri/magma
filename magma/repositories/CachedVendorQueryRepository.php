<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\VendorQueryInterface;

use Magma\dto\VendorDTO;

/**
 * Title: Cached Vendor Query Repository
 * Purpose:
 * - Decorates the base vendor query repository to cache frequent reads.
 * - Handles caching specific to the primary vendor entity.
 * - Coordinates between the cache store (Redis) and the database repository.
 * Why/Why this design:
 * - Implements the Decorator pattern to add caching transparently without modifying the base logic.
 * - Uses the read-through cache pattern to alleviate database load for highly accessed entities.
 * Teaching notes:
 * - A classic example of separation of concerns where caching logic is entirely segregated from data fetching.
 * - Industry standard approach for mitigating high-read throughput bottlenecks in multi-tenant architectures.
 */
class CachedVendorQueryRepository implements VendorQueryInterface
{
    private VendorQueryInterface $repository;
    private ?\Redis $redis;
    private int $primaryVendorId;

    public function __construct(
        VendorQueryInterface $repository,
        ?\Redis $redis = null,
        int $primaryVendorId = 1
    ) {
        $this->repository = $repository;
        $this->redis = $redis;
        $this->primaryVendorId = $primaryVendorId;
    }

    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    public function find(int $id): ?VendorDTO
    {
        return $this->repository->find($id);
    }

    /**
     * Retrieves the primary vendor, leveraging the Redis cache layer.
     * 
     * 1. Checks if a cached instance exists in Redis using the vendor ID key.
     * 2. Returns the unserialized cached data if found.
     * 3. Falls back to the base repository to fetch the data from the database.
     * 4. Caches the newly fetched vendor object in Redis for 24 hours.
     * 
     * Logic behind the logic: This read-through cache minimizes DB calls for the primary vendor entity, which is frequently accessed on almost every request, thus drastically improving overall response times.
     */
    public function getPrimaryVendor(): ?VendorDTO
    {
        $cacheKey = "vendor:{$this->primaryVendorId}";

        if ($this->redis) {
            $cached = $this->redis->get($cacheKey);
            if ($cached !== false) {
                return unserialize($cached, ['allowed_classes' => [\Magma\dto\VendorDTO::class]]);
            }
        }

        $vendor = $this->repository->getPrimaryVendor();

        if ($this->redis && $vendor) {
            $this->redis->setex($cacheKey, 86400, serialize($vendor));
        }

        return $vendor;
    }
}
