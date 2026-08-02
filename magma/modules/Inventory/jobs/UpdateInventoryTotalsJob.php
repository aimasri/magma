<?php

namespace Magma\modules\Inventory\jobs;

use Magma\queue\JobInterface;
use Magma\modules\Inventory\models\VendorInventoryRepositoryInterface;

/**
 * Update Inventory Totals Job
 *
 * Purpose:
 * - Act as the asynchronous projector in our CQRS architecture.
 * - Respond to ledger insertion events by recalculating and caching the inventory totals.
 *
 * Why/Why this design:
 * - Offloading the calculation to a background worker ensures that the web 
 *   response (when a user logs a transaction) remains fast.
 *
 * Teaching notes:
 * - The payload contains the identifiers (`vendor_id`, `product_id`) along with the delta `quantity`. 
 *   We delegate to the read-model to execute an atomic increment using the delta, ensuring O(1)
 *   performance and thread-safety rather than re-aggregating the entire ledger history.
 */
class UpdateInventoryTotalsJob implements JobInterface
{
    private VendorInventoryRepositoryInterface $inventoryRepository;

    /**
     * Initializes the job with the required read-model repository.
     *
     * @param VendorInventoryRepositoryInterface $inventoryRepository The read-model data store.
     */
    public function __construct(VendorInventoryRepositoryInterface $inventoryRepository)
    {
        $this->inventoryRepository = $inventoryRepository;
    }

    /**
     * Executes the background job logic.
     *
     * Execution Flow:
     * 1. Extract the identifiers and quantity delta from the JSON-decoded payload.
     * 2. Atomically increment the read-model (`VendorInventoryRepository`) using the delta.
     *
     * Logic behind the logic:
     * - Using delta updates avoids an O(N) aggregate query against the ledger.
     */
    public function handle(array $payload): void
    {
        $vendorId = $payload['vendor_id'];
        $productId = $payload['product_id'];
        $quantityDelta = $payload['quantity'];

        $this->inventoryRepository->incrementAvailableQuantity($vendorId, $productId, $quantityDelta);
    }
}
