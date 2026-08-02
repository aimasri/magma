<?php

namespace Magma\modules\Inventory\models;

/**
 * Vendor Inventory Repository Interface
 *
 * Purpose:
 * - Define the contract for the Read Model of the CQRS inventory architecture.
 *
 * Why/Why this design:
 * - Dependency Inversion Principle: Projector jobs should depend on abstractions, 
 *   not concrete SQL implementations. This isolates the domain logic from the 
 *   database layer, making the system testable and strictly modular.
 *
 * Teaching notes:
 * - This interface represents the "Query" side of a CQRS architecture, exposing 
 *   fast O(1) lookups that read from pre-aggregated, materialized tables instead 
 *   of computing data dynamically.
 */
interface VendorInventoryRepositoryInterface
{
    /**
     * Retrieve the cached available quantity for a product.
     *
     * Execution Flow:
     * 1. Query the materialized read-model (`vendor_inventory`) for the pre-calculated quantity.
     * 2. Return the scalar float value, defaulting to 0.0 for new products.
     *
     * Logic behind the logic:
     * - This query executes in constant $O(1)$ time utilizing the unique composite index. It strictly 
     *   shields our database from heavy aggregation load when rendering user-facing product listings.
     *
     * @param int $vendorId The vendor ID.
     * @param int $productId The product ID.
     * @return float The pre-calculated quantity, or 0.0 if no record exists.
     */
    public function getAvailableQuantity(int $vendorId, int $productId): float;

    /**
     * Atomically increments or inserts the cached quantity (Materialized View Projection).
     *
     * Execution Flow:
     * 1. Prepare the `INSERT ... ON CONFLICT DO UPDATE` upsert query.
     * 2. Bind the delta quantity to add.
     * 3. Execute the atomic update against the Write connection.
     *
     * Logic behind the logic:
     * - Using an Upsert instead of checking `SELECT count(*)` followed by an `INSERT` or `UPDATE` 
     *   prevents fatal race conditions if multiple background workers attempt to initialize 
     *   the exact same product's cached total simultaneously. Adding EXCLUDED.quantity_available 
     *   to the existing value ensures atomic delta updates.
     *
     * @param int $vendorId The vendor ID.
     * @param int $productId The product ID.
     * @param float $quantityDelta The delta to add.
     * @return void
     */
    public function incrementAvailableQuantity(int $vendorId, int $productId, float $quantityDelta): void;
}
