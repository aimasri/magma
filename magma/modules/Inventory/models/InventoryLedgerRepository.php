<?php

namespace Magma\modules\Inventory\models;

/**
 * Inventory Ledger Repository
 *
 * Purpose:
 * - Handle the Write Model (Command) for the CQRS inventory architecture.
 * - Append immutable transactions to the `inventory_transactions` ledger.
 *
 * Why/Why this design:
 * - Separating the ledger (write) from the totals (read) ensures that inserting 
 *   a transaction is incredibly fast (append-only) and avoids database locking 
 *   issues that occur when concurrently updating a shared 'totals' row.
 *
 * Teaching notes:
 * - This repository strictly adheres to Event Sourcing principles. Transactions 
 *   are immutable. If an error is made, a compensating transaction (e.g., negative quantity) 
 *   must be added rather than updating an existing row.
 */
class InventoryLedgerRepository extends BaseRepository implements InventoryLedgerRepositoryInterface
{
    /**
     * Append a new transaction to the ledger.
     *
     * Execution Flow:
     * 1. Prepare an INSERT statement targeting `inventory_transactions`.
     * 2. Bind the exact transaction payload, leaving primary key and timestamps to the database.
     * 3. Execute the statement against the Write connection.
     *
     * Logic behind the logic:
     * - Utilizing an append-only transaction log inherently prevents data corruption compared to 
     *   destructive `UPDATE` statements, providing an ironclad audit trail.
     *
     * @param \Magma\modules\Inventory\domain\InventoryMovement $movement The encapsulated movement data.
     * @return void
     */
    public function addTransaction(\Magma\modules\Inventory\domain\InventoryMovement $movement): void {
        $sql = "
            INSERT INTO inventory_transactions 
            (vendor_id, product_id, supplier_id, transaction_type, quantity, unit_price) 
            VALUES (:vendor_id, :product_id, :supplier_id, :transaction_type, :quantity, :unit_price)
        ";

        $stmt = $this->dbWrite->prepare($sql);
        $stmt->execute([
            'vendor_id'        => $movement->getVendorId(),
            'product_id'       => $movement->getProductId(),
            'supplier_id'      => $movement->getSupplierId(),
            'transaction_type' => $movement->getTransactionType(),
            'quantity'         => $movement->getQuantity(),
            'unit_price'       => $movement->getUnitPrice()
        ]);
    }

}
