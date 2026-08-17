<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\models\AbstractCommandRepository;
use PDO;

/**
 * Title: Vendor Inventory Command Repository
 * Purpose:
 * - Handles specialized write operations related to vendor inventory synchronization.
 * Why/Why this design:
 * - Separates domain-specific bulk operations (inventory sync) from standard CRUD repositories.
 * - Adheres strictly to CQRS by only targeting the write-master connection.
 * Teaching notes:
 * - The `syncVendorInventory` method leverages `INSERT ... ON CONFLICT DO UPDATE`, which is a highly efficient, atomic "upsert" pattern.
 */
class VendorInventoryCommandRepository extends AbstractCommandRepository
{
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
