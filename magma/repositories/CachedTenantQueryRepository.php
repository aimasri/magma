<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\interfaces\cqrs\TenantQueryInterface;
use Magma\dto\TenantDTO;
use Redis;
use Throwable;

/**
 * Title: Cached Tenant Query Repository
 *
 * Purpose:
 * - Decorates the base tenant query repository to cache frequent reads in Redis.
 * - Handles caching specific to the primary tenant entity with automatic deserialization fault tolerance.
 * - Coordinates between the cache store (Redis) and the database repository.
 *
 * Why / Why this design:
 * - Implements the Decorator pattern to add caching transparently without modifying the base repository.
 * - Deserialization Resilience: Prevents fatal TypeErrors when cached Redis payloads are corrupted, stale, or fail `unserialize()` by gracefully falling back to a fresh database query and evicting the corrupted cache key.
 *
 * Teaching notes:
 * - In high-concurrency SaaS apps, cache deserialization mismatch is a common cause of 500 crashes after code deployments. A robust decorator must never trust cached binary strings unconditionally.
 */
class CachedTenantQueryRepository implements TenantQueryInterface
{
    private TenantQueryInterface $repository;
    private ?Redis $redis;
    private int $primaryTenantId;

    /**
     * Initializes the cached tenant query repository decorator.
     *
     * Logic behind the logic:
     * - Wraps a concrete TenantQueryInterface implementation, adding an optional Redis caching layer.
     * - By allowing Redis to be nullable, the application can degrade gracefully if the cache server is unavailable.
     *
     * @param TenantQueryInterface $repository The base repository to decorate.
     * @param Redis|null $redis Optional Redis instance for caching.
     * @param int $primaryTenantId The default ID used for the primary tenant.
     */
    public function __construct(
        TenantQueryInterface $repository,
        ?Redis $redis = null,
        int $primaryTenantId = 1
    ) {
        $this->repository = $repository;
        $this->redis = $redis;
        $this->primaryTenantId = $primaryTenantId;
    }

    /**
     * @return iterable<int, TenantDTO>
     */
    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        return $this->repository->getAll($limit, $lastId);
    }

    /**
     * Finds a tenant by ID, utilizing the Redis cache to reduce database load.
     *
     * Execution Flow:
     * 1. Constructs a unique cache key for the given tenant ID.
     * 2. Attempts to retrieve and deserialize the tenant data from Redis.
     * 3. If the cache hits and deserializes successfully into a TenantDTO, returns it immediately.
     * 4. If the cache misses or contains corrupted data, evicts the invalid key and queries the base repository.
     * 5. Caches the freshly retrieved TenantDTO in Redis with a 24-hour TTL.
     * 6. Returns the fetched tenant.
     *
     * Logic behind the logic:
     * - The @unserialize suppression and explicit instanceof check prevent fatal application errors
     *   caused by stale or corrupted cache data (e.g., after a class structure changes).
     *
     * @param int $id The tenant identifier.
     * @return TenantDTO|null
     */
    public function find(int $id): ?TenantDTO
    {
        $cacheKey = "tenant:{$id}";

        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached !== false && is_string($cached)) {
                    $unserialized = @unserialize($cached, ['allowed_classes' => [TenantDTO::class]]);
                    if ($unserialized instanceof TenantDTO) {
                        return $unserialized;
                    }
                    $this->redis->del($cacheKey);
                }
            } catch (Throwable $e) {
                error_log("Redis cache read/deserialization failed for tenant {$id}: " . $e->getMessage());
            }
        }

        $tenant = $this->repository->find($id);

        if ($this->redis && $tenant instanceof TenantDTO) {
            try {
                $this->redis->setex($cacheKey, 86400, serialize($tenant));
            } catch (Throwable $e) {
                error_log("Redis cache write failed for tenant {$id}: " . $e->getMessage());
            }
        }

        return $tenant;
    }

    /**
     * Retrieves the primary tenant, leveraging the Redis cache layer with safe fallback.
     *
     * Execution Flow:
     * 1. Checks if a cached instance exists in Redis using the tenant ID key.
     * 2. Safely attempts to unserialize the cached data with type checks.
     * 3. If invalid or corrupted, evicts the key and falls back to the database repository.
     * 4. Caches the newly fetched tenant DTO in Redis for 24 hours.
     *
     * @return TenantDTO|null
     */
    public function getPrimaryTenant(): ?TenantDTO
    {
        $cacheKey = "tenant:{$this->primaryTenantId}";

        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached !== false && is_string($cached)) {
                    $unserialized = @unserialize($cached, ['allowed_classes' => [TenantDTO::class]]);
                    if ($unserialized instanceof TenantDTO) {
                        return $unserialized;
                    }
                    // Evict corrupted or outdated cache entry
                    $this->redis->del($cacheKey);
                }
            } catch (Throwable $e) {
                error_log("Redis cache read/deserialization failed: " . $e->getMessage());
            }
        }

        $tenant = $this->repository->getPrimaryTenant();

        if ($this->redis && $tenant instanceof TenantDTO) {
            try {
                $this->redis->setex($cacheKey, 86400, serialize($tenant));
            } catch (Throwable $e) {
                error_log("Redis cache write failed: " . $e->getMessage());
            }
        }

        return $tenant;
    }
}
