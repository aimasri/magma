<?php

namespace Magma\services;

use Magma\modules\Inventory\models\VendorInventoryRepositoryInterface;
use Magma\queue\QueueInterface;
use Magma\modules\Inventory\jobs\SyncVendorInventoryJob;

/**
 * Title: Inventory Sync Service
 *
 * Purpose:
 * - Orchestrates the synchronization of vendor inventory levels.
 * - Coordinates between the inventory repository and the queueing system.
 *
 * Why this design:
 * - Facade/Application Service pattern: Hides the complexity of fetching vendors and pushing jobs to the queue.
 * - Decouples the initiation of the sync process from the actual execution of the sync logic (which is deferred to a queue job).
 *
 * Teaching notes:
 * - Batching jobs via a queue is critical for performance and scalability in enterprise systems.
 * - Consider adding idempotency keys or job deduplication if this runs via a cron schedule to avoid duplicate jobs.
 */
class InventorySyncService 
{
    private VendorInventoryRepositoryInterface $repository;
    private QueueInterface $queue;

    public function __construct(VendorInventoryRepositoryInterface $repository, QueueInterface $queue)
    {
        $this->repository = $repository;
        $this->queue = $queue;
    }

    /**
     * Iterates over all active vendors and queues a job to sync their inventory ledger.
     *
     * Execution Flow:
     * 1. Retrieve a list of vendor IDs that have recent transaction activity from the repository.
     * 2. Loop through each vendor ID.
     * 3. Instantiate and push a SyncVendorInventoryJob onto the queue for asynchronous processing.
     *
     * Logic behind the logic:
     * - Asynchronous processing: Offloads heavy inventory calculations to background workers, preventing the web request from blocking or timing out.
     */
    public function syncAllVendors(): void
    {
        $vendors = $this->repository->getVendorIdsFromTransactions();
        
        foreach ($vendors as $vendorId) {
            $this->queue->push(new SyncVendorInventoryJob($this->repository, $vendorId));
        }
    }
}
