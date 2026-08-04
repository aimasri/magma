<?php

namespace Magma\repositories;

use Magma\database\BaseCommandRepository;

use PDO;

/**
 * Title: Vendor Inventory Repository
 * Purpose:
 * - Handles specialized read and write operations related to vendor inventory synchronization.
 * - Coordinates the fetching of vendor IDs that require sync and executes the actual sync SQL.
 * Why/Why this design:
 * - Separates domain-specific bulk operations (inventory sync) from standard CRUD repositories.
 * - Uses raw SQL aggregation for performance, bypassing ORM overhead for massive data processing tasks.
 * Teaching notes:
 * - The `syncVendorInventory` method leverages `INSERT ... ON CONFLICT DO UPDATE`, which is a highly efficient, atomic "upsert" pattern standard in modern PostgreSQL/MySQL data warehousing.
 */
class VendorInventoryRepository extends BaseCommandRepository
{
    /**
     * Fetch unique vendor IDs to process the sync in chunks, preventing an unbounded full-table scan.
     */
    public function getVendorIdsFromTransactions(): array
    {
        $stmt = $this->getDb()->prepare("SELECT id FROM vendors");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Synchronize inventory for a specific vendor.
     */
    public function syncVendorInventory(int $vendorId): void
    {
        $sql = "
            INSERT INTO vendor_inventory (vendor_id, product_id, quantity_available)
            SELECT vendor_id, product_id, SUM(quantity) as quantity_available
            FROM inventory_transactions
            WHERE vendor_id = :vendor_id
            GROUP BY vendor_id, product_id
            ON CONFLICT (vendor_id, product_id) 
            DO UPDATE SET quantity_available = EXCLUDED.quantity_available
        ";
        
        $syncStmt = $this->getDb()->prepare($sql);
        $syncStmt->execute(['vendor_id' => $vendorId]);
    }
}
