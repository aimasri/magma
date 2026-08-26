<?php

declare(strict_types=1);

namespace Magma\view\dashboard;

use Magma\interfaces\dashboard\DashboardWidgetInterface;

/**
 * Title: Dashboard Widget Registry
 *
 * Purpose:
 * - A central registry to hold, order, and safely execute registered dashboard widgets.
 * - Protects non-UI environments (CLI migrations, background queue workers) and partial outages
 *   via soft-dependency protection and isolated try/catch boundaries.
 *
 * Why / Why this design:
 * - Registry & Strategy Pattern: Provides a centralized dispatcher for decoupled widget plugins.
 * - Open/Closed Principle (OCP): Allows adding new dashboard metrics by simply registering a new
 *   widget in a module ServiceProvider without modifying the DashboardController or any existing classes.
 * - Soft-Dependency Isolation: If a widget's dependency is missing in a CLI context or fails at runtime,
 *   it is caught, logged, and gracefully omitted, preventing a total dashboard 500 error.
 *
 * Teaching notes:
 * - Inject this registry into the controller, call `renderAll($vendorId)`, and pass the resulting array directly to the View layer.
 */
class DashboardWidgetRegistry
{
    /** @var array<string, DashboardWidgetInterface|callable|string> Registered widgets or widget factories keyed by identifier. */
    private array $widgets = [];

    /**
     * Registers a new widget into the registry. Supports instances, class strings, or factory closures.
     *
     * Execution Flow:
     * 1. If an instance is passed, extract identifier and store.
     * 2. If a class name or closure is passed with an explicit identifier, register lazily.
     * 3. Soft-dependency protection: Wrap in try/catch to ensure non-UI environments boot cleanly.
     *
     * @param DashboardWidgetInterface|string|callable $widget Widget instance, class name, or factory closure.
     * @param string|null $identifier Optional explicit identifier if registering a class name or closure.
     * @return void
     */
    public function register(DashboardWidgetInterface|string|callable $widget, ?string $identifier = null): void
    {
        try {
            if ($widget instanceof DashboardWidgetInterface) {
                $this->widgets[$widget->getIdentifier()] = $widget;
                return;
            }

            if (is_string($widget) && class_exists($widget)) {
                $key = $identifier ?? $widget;
                $this->widgets[$key] = $widget;
                return;
            }

            if (is_callable($widget)) {
                if (empty($identifier)) {
                    throw new \InvalidArgumentException("A widget identifier must be provided when registering a callable factory.");
                }
                $this->widgets[$identifier] = $widget;
                return;
            }
        } catch (\Throwable $e) {
            error_log("DashboardWidgetRegistry soft-registration skipped: " . $e->getMessage());
        }
    }

    /**
     * Executes all registered widgets in sequence for a specific tenant.
     *
     * Execution Flow:
     * 1. Iterates over all registered widgets.
     * 2. Resolves lazy class strings or closures into DashboardWidgetInterface instances.
     * 3. Invokes their `render()` method, injecting the current tenant's ID.
     * 4. Collects the resulting datasets into a master associative array sorted by widget order.
     * 5. Traps and logs individual widget exceptions so other widgets render unimpeded.
     *
     * @param int $vendorId The tenant context.
     * @return array<string, array<string, mixed>> Associative array of [widgetIdentifier => widgetData].
     */
    public function renderAll(int $vendorId): array
    {
        $rendered = [];

        foreach ($this->widgets as $identifier => $entry) {
            try {
                $widget = $this->resolveWidget($entry);
                if ($widget === null) {
                    continue;
                }

                $data = $widget->render($vendorId);
                $rendered[$widget->getIdentifier()] = [
                    'identifier' => $widget->getIdentifier(),
                    'title' => $widget->getTitle(),
                    'order' => $widget->getOrder(),
                    'data' => $data,
                ];
            } catch (\Throwable $e) {
                // Log the error internally, but allow the rest of the dashboard to render
                error_log("Dashboard Widget Error [{$identifier}]: " . $e->getMessage());
                $rendered[$identifier] = [
                    'identifier' => (string) $identifier,
                    'title' => 'Widget Unavailable',
                    'order' => 999,
                    'data' => ['error' => 'Widget temporarily unavailable'],
                ];
            }
        }

        // Sort widgets by order weight
        uasort($rendered, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return $rendered;
    }

    /**
     * Checks if a widget with the given identifier is registered.
     *
     * @param string $identifier
     * @return bool
     */
    public function hasWidget(string $identifier): bool
    {
        return isset($this->widgets[$identifier]);
    }

    /**
     * Retrieves an individual widget instance if resolvable.
     *
     * @param string $identifier
     * @return DashboardWidgetInterface|null
     */
    public function getWidget(string $identifier): ?DashboardWidgetInterface
    {
        if (!isset($this->widgets[$identifier])) {
            return null;
        }

        try {
            return $this->resolveWidget($this->widgets[$identifier]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolves a widget entry into a DashboardWidgetInterface instance.
     *
     * @param DashboardWidgetInterface|string|callable $entry
     * @return DashboardWidgetInterface|null
     */
    private function resolveWidget(mixed $entry): ?DashboardWidgetInterface
    {
        if ($entry instanceof DashboardWidgetInterface) {
            return $entry;
        }

        if (is_string($entry) && class_exists($entry)) {
            $instance = new $entry();
            if ($instance instanceof DashboardWidgetInterface) {
                return $instance;
            }
        }

        if (is_callable($entry)) {
            $instance = $entry();
            if ($instance instanceof DashboardWidgetInterface) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Clears all registered widgets.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->widgets = [];
    }
}
