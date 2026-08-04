<?php

namespace Magma\view\dashboard;

use Magma\interfaces\dashboard\DashboardWidgetInterface;

/**
 * Title: Dashboard Widget Registry
 *
 * Purpose:
 * - A registry to hold and execute registered dashboard widgets.
 *
 * Why this design:
 * - Registry Pattern: Provides a centralized dispatcher for decoupled widget plugins.
 * - Open/Closed Principle: Allows adding new dashboard metrics by simply registering a new class, without modifying the DashboardController or any existing classes.
 *
 * Teaching notes:
 * - Inject this registry into the controller, call `renderAll()`, and pass the resulting array directly to the View layer.
 */
class DashboardWidgetRegistry
{
    /** @var DashboardWidgetInterface[] */
    private array $widgets = [];

    /**
     * Registers a new widget into the registry.
     *
     * @param DashboardWidgetInterface $widget
     * @return void
     */
    public function register(DashboardWidgetInterface $widget): void
    {
        $this->widgets[$widget->getIdentifier()] = $widget;
    }

    /**
     * Executes all registered widgets in sequence.
     * 
     * 1. Iterates over all registered widgets.
     * 2. Invokes their `render()` method, injecting the current tenant's ID.
     * 3. Collects the resulting datasets into a master associative array keyed by widget identifiers.
     * 
     * Logic behind the logic:
     * - This centralizes the map-reduce iteration for dashboard generation, keeping controllers ultra-thin.
     *
     * @param int $vendorId
     * @return array Associative array of widgetIdentifier => widgetData
     */
    public function renderAll(int $vendorId): array
    {
        $data = [];
        foreach ($this->widgets as $identifier => $widget) {
            try {
                $data[$identifier] = $widget->render($vendorId);
            } catch (\Throwable $e) {
                // Log the error internally, but allow the rest of the dashboard to render
                error_log("Dashboard Widget Error [{$identifier}]: " . $e->getMessage());
                $data[$identifier] = ['error' => 'Widget temporarily unavailable'];
            }
        }
        return $data;
    }
}
