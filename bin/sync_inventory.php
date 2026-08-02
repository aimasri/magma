#!/usr/bin/env php
<?php

/**
 * Title: Inventory Synchronization Script
 *
 * Purpose:
 * - Aggregates transaction history from `inventory_transactions`.
 * - Upserts current available quantities into `vendor_inventory`.
 * - Prevents unbounded full-table scans by chunking updates per vendor.
 *
 * Why / Why this design:
 * - Employs a Command-Line Interface (CLI) pattern for background execution.
 * - By decoupling read-heavy sync operations from web requests, we ensure 
 *   high availability and prevent HTTP timeouts for large datasets.
 * - Uses ON CONFLICT for atomic upserts, reducing race conditions compared 
 *   to separate check-and-insert queries.
 *
 * Teaching notes:
 * - This acts as a rudimentary materialized view builder. In more robust 
 *   architectures, this could be handled by database triggers, Debezium (CDC), 
 *   or asynchronous event streams (e.g., Kafka).
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

require __DIR__ . '/../magma/core/config/bootstrap.php';

use Magma\services\InventorySyncService;

try {
    $syncService = $container->get(InventorySyncService::class);
    $syncService->syncAllVendors();

    echo "Successfully synchronized vendor_inventory with inventory_transactions ledger.\n";
} catch (\Throwable $e) {
    echo "Failed to synchronize: " . $e->getMessage() . "\n";
    exit(1);
}
