<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\models\AbstractQueryRepository;
use PDO;

/**
 * Title: Vendor Inventory Query Repository
 * Purpose:
 * - Handles specialized read operations related to vendor inventory synchronization.
 * Why/Why this design:
 * - Strict CQRS Segregation ensures reads only target the read-replica connections.
 * Teaching notes:
 * - Fetch unique vendor IDs to process the sync in chunks, preventing unbounded full-table scans.
 */
class VendorInventoryQueryRepository extends AbstractQueryRepository
{
    /**
     * Fetch unique vendor IDs.
     * @return array<int, int>
     */
    public function getVendorIdsFromTransactions(): array
    {
        $stmt = $this->getDb()->prepare("SELECT DISTINCT vendor_id FROM inventory_transactions");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
