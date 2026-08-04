<?php

namespace Magma\modules\Inventory\models;

/**
 * Vendor Inventory Repository
 *
 * Purpose:
 * - Handle the Read Model (Query) for the CQRS inventory architecture.
 * - Serve as a Materialized View for `vendor_inventory`, providing instant O(1) reads.
 *
 * Why/Why this design:
 * - Instead of calculating the `SUM()` of thousands of ledger transactions every time 
 *   a user visits their dashboard, this repository fetches a pre-calculated total.
 * - The data in this table is eventually consistent, updated asynchronously by the queue.
 *
 * Teaching notes:
 * - The `incrementAvailableQuantity()` method uses PostgreSQL's `ON CONFLICT DO UPDATE` 
 *   (an Upsert). This guarantees atomicity and prevents race conditions if multiple 
 *   jobs attempt to initialize the same product's inventory record simultaneously.
 */
class VendorInventoryRepository extends \Magma\database\BaseRepository implements VendorInventoryRepositoryInterface
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
    public function getAvailableQuantity(int $vendorId, int $productId): float
    {
        $sql = "
            SELECT quantity_available 
            FROM vendor_inventory 
            WHERE vendor_id = :vendor_id AND product_id = :product_id
        ";

        $stmt = $this->getDbRead()->prepare($sql);
        $stmt->execute([
            'vendor_id'  => $vendorId,
            'product_id' => $productId
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (float)$result['quantity_available'] : 0.0;
    }

    public function recalculateAvailableQuantity(int $vendorId, int $productId): void
    {
        $sql = "
            INSERT INTO vendor_inventory (vendor_id, product_id, quantity_available)
            SELECT :vendor_id, :product_id, COALESCE(SUM(quantity), 0)
            FROM inventory_transactions
            WHERE vendor_id = :vendor_id AND product_id = :product_id
            ON CONFLICT (vendor_id, product_id) 
            DO UPDATE SET quantity_available = EXCLUDED.quantity_available
        ";

        $stmt = $this->getDbWrite()->prepare($sql);
        $stmt->execute([
            'vendor_id'  => $vendorId,
            'product_id' => $productId
        ]);
    }
}
