<?php

namespace Magma\modules\Inventory\jobs;

use Magma\queue\JobInterface;
use Magma\modules\Inventory\models\VendorInventoryRepositoryInterface;

/**
 * Title: Sync Vendor Inventory Job
 * Purpose:
 * - Encapsulates the logic for asynchronously synchronizing a vendor's inventory ledger.
 * - Coordinates the execution of the repository sync method based on the queue payload.
 * Why/Why this design:
 * - Implements the Command pattern specific to background processing (Jobs).
 * - Offloads potentially slow, heavy database aggregations from the main synchronous request thread, preventing N+1 blockages.
 * Teaching notes:
 * - Breaking heavy operations into individual queued jobs per vendor prevents a single massive sync process from locking up database tables or timing out.
 */
class SyncVendorInventoryJob implements JobInterface
{
    private VendorInventoryRepositoryInterface $repository;
    public function __construct(VendorInventoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Executes the background job logic.
     *
     * @param array $payload The JSON-decoded payload from the queue.
     */
    public function handle(array $payload): void
    {
        $vendorId = $payload['vendor_id'] ?? null;
        if ($vendorId) {
            $this->repository->syncVendorInventory((int) $vendorId);
        }
    }
}
