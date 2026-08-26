<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\interfaces\cqrs\VendorQueryInterface;
use Magma\dto\VendorDTO;
use Redis;
use Throwable;

/**
 * Title: Cached Vendor Query Repository
 *
 * Purpose:
 * - Decorates the base vendor query repository to cache frequent reads in Redis.
 * - Handles caching specific to the primary vendor entity with automatic deserialization fault tolerance.
 * - Coordinates between the cache store (Redis) and the database repository.
 *
 * Why / Why this design:
 * - Implements the Decorator pattern to add caching transparently without modifying the base repository.
 * - Deserialization Resilience: Prevents fatal TypeErrors when cached Redis payloads are corrupted, stale, or fail `unserialize()` by gracefully falling back to a fresh database query and evicting the corrupted cache key.
 *
 * Teaching notes:
 * - In high-concurrency SaaS apps, cache deserialization mismatch is a common cause of 500 crashes after code deployments. A robust decorator must never trust cached binary strings unconditionally.
 */
class CachedVendorQueryRepository implements VendorQueryInterface
{
    private VendorQueryInterface $repository;
    private ?Redis $redis;
    private int $primaryVendorId;

    public function __construct(
        VendorQueryInterface $repository,
        ?Redis $redis = null,
        int $primaryVendorId = 1
    ) {
        $this->repository = $repository;
        $this->redis = $redis;
        $this->primaryVendorId = $primaryVendorId;
    }

    /**
     * @return iterable<int, VendorDTO>
     */
    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    public function find(int $id): ?VendorDTO
    {
        $cacheKey = "vendor:{$id}";

        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached !== false && is_string($cached)) {
                    $unserialized = @unserialize($cached, ['allowed_classes' => [VendorDTO::class]]);
                    if ($unserialized instanceof VendorDTO) {
                        return $unserialized;
                    }
                    $this->redis->del($cacheKey);
                }
            } catch (Throwable $e) {
                error_log("Redis cache read/deserialization failed for vendor {$id}: " . $e->getMessage());
            }
        }

        $vendor = $this->repository->find($id);

        if ($this->redis && $vendor instanceof VendorDTO) {
            try {
                $this->redis->setex($cacheKey, 86400, serialize($vendor));
            } catch (Throwable $e) {
                error_log("Redis cache write failed for vendor {$id}: " . $e->getMessage());
            }
        }

        return $vendor;
    }

    /**
     * Retrieves the primary vendor, leveraging the Redis cache layer with safe fallback.
     *
     * Execution Flow:
     * 1. Checks if a cached instance exists in Redis using the vendor ID key.
     * 2. Safely attempts to unserialize the cached data with type checks.
     * 3. If invalid or corrupted, evicts the key and falls back to the database repository.
     * 4. Caches the newly fetched vendor DTO in Redis for 24 hours.
     *
     * @return VendorDTO|null
     */
    public function getPrimaryVendor(): ?VendorDTO
    {
        $cacheKey = "vendor:{$this->primaryVendorId}";

        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached !== false && is_string($cached)) {
                    $unserialized = @unserialize($cached, ['allowed_classes' => [VendorDTO::class]]);
                    if ($unserialized instanceof VendorDTO) {
                        return $unserialized;
                    }
                    // Evict corrupted or outdated cache entry
                    $this->redis->del($cacheKey);
                }
            } catch (Throwable $e) {
                error_log("Redis cache read/deserialization failed: " . $e->getMessage());
            }
        }

        $vendor = $this->repository->getPrimaryVendor();

        if ($this->redis && $vendor instanceof VendorDTO) {
            try {
                $this->redis->setex($cacheKey, 86400, serialize($vendor));
            } catch (Throwable $e) {
                error_log("Redis cache write failed: " . $e->getMessage());
            }
        }

        return $vendor;
    }
}
