<?php

namespace Magma\models;

/**
 * Vendor Repository Interface
 *
 * Purpose:
 * - Defines the strict contract for accessing vendor configuration and metadata.
 *
 * Why / Why this design:
 * - Extracting an interface for the repository allows us to safely introduce caching layers
 *   (via the Decorator Pattern) or swap database drivers without breaking the controllers 
 *   or services that depend on vendor data.
 *
 * Teaching notes:
 * - Always type-hint dependencies against interfaces (e.g., `VendorRepositoryInterface`) 
 *   rather than concrete classes (e.g., `VendorRepository`) to fully leverage polymorphism 
 *   and maintain the Dependency Inversion Principle.
 */
interface VendorRepositoryInterface
{
    public function create(array $data): bool;
    public function getAll(int $limit = 100, ?int $lastId = null): iterable;
    public function find(int $id): ?array;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getPrimaryVendor(): ?array;
}
