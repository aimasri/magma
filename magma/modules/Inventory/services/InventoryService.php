<?php

namespace Magma\modules\Inventory\services;

use Magma\modules\Inventory\models\InventoryLedgerRepositoryInterface;
use Magma\queue\QueueInterface;
use Magma\database\TransactionManagerInterface;
use Magma\modules\Inventory\jobs\UpdateInventoryTotalsJob;

/**
 * Inventory Service
 *
 * Purpose:
 * - Act as the Domain Service for orchestrating inventory movements.
 * - Serve as the gateway for the CQRS write-pipeline.
 *
 * Why/Why this design:
 * - Controllers should not coordinate the complex dance of inserting a transaction 
 *   and firing a background queue job. By wrapping this in a Service, we make it 
 *   re-usable across Web Controllers, API endpoints, and CLI scripts.
 *
 * Teaching notes:
 * - Observe the separation of concerns: The service doesn't know *how* to save 
 *   to the database (the Repository handles that) and it doesn't know *how* to 
 *   talk to Redis (the QueueInterface handles that). It only knows the business rules.
 */
class InventoryService
{
    private InventoryLedgerRepositoryInterface $ledgerRepository;
    private QueueInterface $queue;

    /**
     * Initializes the service with its required dependencies.
     *
     * @param InventoryLedgerRepositoryInterface $ledgerRepository Data store for transactions.
     * @param QueueInterface $queue System for async job dispatch.
     */
    public function __construct(
        InventoryLedgerRepositoryInterface $ledgerRepository,
        QueueInterface $queue
    ) {
        $this->ledgerRepository = $ledgerRepository;
        $this->queue = $queue;
    }

    /**
     * Record a movement of inventory and trigger the async projection.
     *
     * Execution Flow:
     * 1. Instantiate the InventoryMovement domain entity.
     * 2. The write-model (Ledger Repository) inserts the transaction using the entity.
     * 3. The job payload is structured using the entity's properties.
     * 4. The `UpdateInventoryTotalsJob` is pushed to the queue to eventually update the read-model.
     *
     * Logic behind the logic:
     * - Side-effects (like pushing to Redis) MUST occur after the database operation. 
     *   We do not wrap the `addTransaction` call in an explicit transaction block because 
     *   a single `INSERT` statement is inherently atomic in PostgreSQL. Wrapping it would 
     *   needlessly triple the database round-trips (`BEGIN` -> `INSERT` -> `COMMIT`).
     *
     * @param int $vendorId The vendor ID.
     * @param int $productId The product ID.
     * @param float $quantity The amount moved (positive or negative).
     * @param string $transactionType The classification (e.g., 'restock', 'spoilage').
     * @param int|null $supplierId The supplier (for restocks).
     * @param float|null $unitPrice The cost per unit.
     * @return void
     */
    public function recordMovement(\Magma\modules\Inventory\domain\RecordInventoryMovementCommand $command): void {
        $movement = new \Magma\modules\Inventory\domain\InventoryMovement(
            $command->vendorId,
            $command->productId,
            $command->quantity,
            $command->transactionType,
            $command->supplierId,
            $command->unitPrice
        );

        // 1. Write the immutable event to the ledger (Single statement, inherently atomic)
        $this->ledgerRepository->addTransaction($movement);

        // 2. Dispatch the projection job ONLY after successful database write
        $payload = json_encode([
            \Magma\queue\JobInterface::HANDLER_KEY => UpdateInventoryTotalsJob::class,
            \Magma\queue\JobInterface::PAYLOAD_KEY => [
                'vendor_id'  => $movement->getVendorId(),
                'product_id' => $movement->getProductId(),
                'quantity'   => $movement->getQuantity()
            ]
        ]);

        $this->queue->push('default', $payload);
    }
}
