#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

require __DIR__ . '/../fussy_app/core/config/bootstrap.php';

use core\database\Database;

try {
    $dbWrite = Database::getWriteConnection();

    // Fetch unique vendor IDs to process the sync in chunks, preventing an unbounded full-table scan
    $stmt = $dbWrite->prepare("SELECT DISTINCT vendor_id FROM inventory_transactions");
    $stmt->execute();
    $vendors = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    $sql = "
        INSERT INTO vendor_inventory (vendor_id, product_id, quantity_available)
        SELECT vendor_id, product_id, SUM(quantity) as quantity_available
        FROM inventory_transactions
        WHERE vendor_id = :vendor_id
        GROUP BY vendor_id, product_id
        ON CONFLICT (vendor_id, product_id) 
        DO UPDATE SET quantity_available = EXCLUDED.quantity_available
    ";
    
    $syncStmt = $dbWrite->prepare($sql);

    foreach ($vendors as $vendorId) {
        $syncStmt->execute(['vendor_id' => $vendorId]);
    }

    echo "Successfully synchronized vendor_inventory with inventory_transactions ledger.\n";
} catch (\Exception $e) {
    echo "Failed to synchronize: " . $e->getMessage() . "\n";
}
