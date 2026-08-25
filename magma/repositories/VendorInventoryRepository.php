<?php

/**
 * Title: VendorInventoryRepository (Deprecated)
 *
 * Purpose:
 * - Previously handled database operations for vendor inventory.
 * - This file is deprecated and currently serves only as a tombstone.
 *
 * Why / Why this design:
 * - Transitioned to CQRS architecture.
 * - Split into VendorInventoryQueryRepository and VendorInventoryCommandRepository to enforce the Single Responsibility Principle.
 *
 * Teaching notes:
 * - Any operations regarding vendor inventory must use the appropriate Query or Command repository instead.
 */
// This file is deprecated. VendorInventoryRepository has been split into VendorInventoryQueryRepository and VendorInventoryCommandRepository for strict CQRS segregation.
