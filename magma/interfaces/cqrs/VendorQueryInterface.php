<?php

namespace Magma\interfaces\cqrs;

/**
 * Title: Vendor Query Interface
 * Purpose:
 * - Defines the contract for all read operations (queries) related to Vendor entities.
 * - Handles fetching single vendors, multiple vendors, and the primary system vendor.
 * Why/Why this design:
 * - Enforces CQRS by completely segregating read concerns from write concerns.
 * - Makes it trivial to swap out read implementations (e.g., moving from a direct DB query to a search index like Elasticsearch) without changing consuming code.
 * Teaching notes:
 * - By defining a clear query contract, we easily enable the Decorator pattern (like caching) since decorators only need to implement these read methods.
 */
interface VendorQueryInterface
{
    public function getAll(int $limit = 100, ?int $lastId = null): iterable;
    public function find(int $id): ?\Magma\dto\VendorDTO;
    public function getPrimaryVendor(): ?\Magma\dto\VendorDTO;
}
