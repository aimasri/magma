<?php

namespace Magma\interfaces\cqrs;

/**
 * Title: Tenant Query Interface
 * Purpose:
 * - Defines the contract for all read operations (queries) related to Tenant entities.
 * - Handles fetching single tenants, multiple tenants, and the primary system tenant.
 * Why/Why this design:
 * - Enforces CQRS by completely segregating read concerns from write concerns.
 * - Makes it trivial to swap out read implementations (e.g., moving from a direct DB query to a search index like Elasticsearch) without changing consuming code.
 * Teaching notes:
 * - By defining a clear query contract, we easily enable the Decorator pattern (like caching) since decorators only need to implement these read methods.
 */
interface TenantQueryInterface
{
    /**
     * @param int $limit
     * @param int|null $lastId
     * @return iterable<int, \Magma\dto\TenantDTO>
     */
    public function getAll(int $limit = 100, ?int $lastId = null): iterable;
    public function find(int $id): ?\Magma\dto\TenantDTO;
    public function getPrimaryTenant(): ?\Magma\dto\TenantDTO;
}
