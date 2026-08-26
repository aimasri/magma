<?php

declare(strict_types=1);

namespace Magma\interfaces\dashboard;

/**
 * Title: Dashboard Widget Interface
 *
 * Purpose:
 * - Defines the contract for an individual dashboard metric / panel widget.
 *
 * Why / Why this design:
 * - Strategy & Plugin Pattern: Applies a modular plugin architecture to dashboard UI metrics.
 * - Controller Decoupling: Prevents the main DashboardController or DashboardService from becoming
 *   a monolithic God Class that aggregates metrics for dozens of distinct domain areas.
 *
 * Teaching notes:
 * - Implement this interface for each new panel or statistic card on a dashboard.
 * - The registry will automatically inject the `$tenantId` (tenant context) to enforce multi-tenancy.
 */
interface DashboardWidgetInterface
{
    /**
     * Gets the unique identifier for the widget.
     *
     * @return string Unique machine name (e.g., 'sales_summary', 'pending_orders', 'inventory_alerts').
     */
    public function getIdentifier(): string;

    /**
     * Executes necessary metric queries and returns the widget's rendered data or view payload.
     *
     * @param int $tenantId The tenant context identifier.
     * @return array<string, mixed> Associative array containing metric data, template name, and title.
     */
    public function render(int $tenantId): array;

    /**
     * Gets the display title for the widget header.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Gets the display order weight (lower numbers render first).
     *
     * @return int
     */
    public function getOrder(): int;
}
