<?php

namespace Magma\interfaces\dashboard;

/**
 * Title: Dashboard Widget Interface
 *
 * Purpose:
 * - Defines the contract for an individual dashboard widget.
 *
 * Why this design:
 * - Strategy/Plugin Pattern: Applies a modular design to dashboard metrics.
 * - Controller Decoupling: Prevents the main DashboardController from becoming a monolithic God Class that aggregates metrics for 15 different domains.
 *
 * Teaching notes:
 * - Implement this interface for each new panel on a dashboard.
 * - The registry will automatically inject the `$vendorId` to enforce multi-tenancy.
 */
interface DashboardWidgetInterface
{
    /**
     * Gets the unique identifier for the widget.
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Executes the necessary queries and returns the widget's data.
     *
     * @param int $vendorId The tenant context.
     * @return array
     */
    public function render(int $vendorId): array;
}
