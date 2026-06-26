<?php

namespace Magma\modules\Inventory\models;

/**
 * Inventory Ledger Repository Interface
 *
 * Purpose:
 * - Define the contract for the Write Model of the CQRS inventory architecture.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle: Services and Jobs should depend on abstractions, 
 *   not concrete SQL-bound implementations. This allows us to mock the ledger for 
 *   unit testing or swap to a different storage engine without touching business logic.
 *
 * Teaching notes:
 * - The ledger is strictly append-only, representing an immutable event stream. This 
 *   is a fundamental principle of Event Sourcing and guarantees data integrity.
 */
interface InventoryLedgerRepositoryInterface
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
    public function addTransaction(\Magma\modules\Inventory\domain\InventoryMovement $movement): void;

}
