<?php

declare(strict_types=1);

namespace Magma\views;

/**
 * Title: Decoupled View Composer Registry
 *
 * Purpose:
 * - Provides a centralized registry for modular view composition, sidebar navigation extensions,
 *   header widget injection, and view variable sharing.
 * - Enables domain modules (e.g. Services, Menu, Inventory) to inject navigation items and context
 *   data into application layouts without modifying core framework files.
 *
 * Why / Why this design:
 * - Open/Closed Principle (OCP): Core layouts (such as `sidebar.php` or `header.php`) remain closed
 *   for modification, yet open for extension by domain modules during application boot.
 * - Modular Monolith Isolation: Keeps application domain concerns strictly within their respective
 *   modules, preventing framework kernel files from becoming entangled with domain-specific markup.
 * - Soft-Dependency Isolation: Composer failures are caught and logged without aborting the HTTP response.
 *
 * Teaching notes:
 * - Register composers and sidebar items in module ServiceProviders during application bootstrap.
 * - Use `compose($view, $data)` inside the controller or middleware pipeline before rendering.
 */
class ViewComposerRegistry
{
    /** @var array<string, array<int, callable|string>> Registered view composers keyed by view name. */
    private array $composers = [];

    /** @var array<int, array{priority: int, item: array<string, mixed>}> Registered sidebar items with priorities. */
    private array $sidebarItems = [];

    /** @var array<int, array{priority: int, widget: mixed}> Registered header widgets with priorities. */
    private array $headerWidgets = [];

    /** @var array<string, mixed> Global data shared across all views. */
    private array $sharedData = [];

    /**
     * Registers a composer callback or class for a specific view or wildcard pattern ('*').
     *
     * Execution Flow:
     * 1. Accept target view name (e.g. 'layouts/default', 'Menu::index', or '*' for all views).
     * 2. Append the composer to the internal registry list for that view key.
     *
     * @param string $view View template name or '*' for global wildcard.
     * @param callable|string $composer Callback function or class name implementing a compose method.
     * @return void
     */
    public function register(string $view, callable|string $composer): void
    {
        $this->composers[$view][] = $composer;
    }

    /**
     * Registers a sidebar navigation item contributed by a domain module.
     *
     * Item array schema:
     * - 'id': string (e.g., 'menu_items', 'staff_management')
     * - 'label': string (e.g., 'Menu Management')
     * - 'url': string (e.g., '/admin/menu')
     * - 'icon': string|null (e.g., 'utensils', 'calendar')
     * - 'order': int (e.g., 10)
     * - 'badge': string|int|null (optional notification count)
     * - 'active_pattern': string|null (regex or prefix for active state matching)
     *
     * @param array<string, mixed> $item The sidebar item specification.
     * @param int $priority Display order weight (lower numbers sort first, default 10).
     * @return void
     */
    public function registerSidebarItem(array $item, int $priority = 10): void
    {
        $this->sidebarItems[] = [
            'priority' => $priority,
            'item' => $item,
        ];
    }

    /**
     * Registers a header widget contributed by a domain module.
     *
     * @param array<string, mixed>|callable|string $widget Header widget definition or renderer.
     * @param int $priority Display order weight (lower numbers sort first, default 10).
     * @return void
     */
    public function registerHeaderWidget(array|callable|string $widget, int $priority = 10): void
    {
        $this->headerWidgets[] = [
            'priority' => $priority,
            'widget' => $widget,
        ];
    }

    /**
     * Globally shares a variable across all composed views.
     *
     * @param string $key Variable key.
     * @param mixed $value Variable value.
     * @return void
     */
    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /**
     * Retrieves all globally shared variables.
     *
     * @return array<string, mixed>
     */
    public function getSharedData(): array
    {
        return $this->sharedData;
    }

    /**
     * Composes and merges data for a specific view template.
     *
     * Execution Flow:
     * 1. Start with global shared data merged with incoming controller data.
     * 2. Find all matching composers: global wildcard '*' composers first, then view-specific composers.
     * 3. Execute each composer in a try/catch block to prevent breaking the render pipeline.
     * 4. Inject aggregated sidebar items and header widgets.
     * 5. Return the consolidated view data dictionary.
     *
     * @param string $view The target view template name.
     * @param array<string, mixed> $data Initial controller data.
     * @return array<string, mixed> Fully composed view data.
     */
    public function compose(string $view, array $data = []): array
    {
        $mergedData = array_merge($this->sharedData, $data);

        // Inject sidebar items and header widgets automatically
        $mergedData['sidebarItems'] = $this->getSidebarItems();
        $mergedData['headerWidgets'] = $this->getHeaderWidgets();

        // 1. Execute global wildcard composers
        if (isset($this->composers['*'])) {
            foreach ($this->composers['*'] as $composer) {
                $mergedData = $this->executeComposer($composer, $view, $mergedData);
            }
        }

        // 2. Execute view-specific composers
        if (isset($this->composers[$view])) {
            foreach ($this->composers[$view] as $composer) {
                $mergedData = $this->executeComposer($composer, $view, $mergedData);
            }
        }

        return $mergedData;
    }

    /**
     * Retrieves all registered sidebar navigation items, sorted by priority and order.
     *
     * @return array<int, array<string, mixed>> Sorted sidebar items.
     */
    public function getSidebarItems(): array
    {
        $items = $this->sidebarItems;

        usort($items, static function (array $a, array $b): int {
            if ($a['priority'] === $b['priority']) {
                $orderA = $a['item']['order'] ?? 0;
                $orderB = $b['item']['order'] ?? 0;
                return $orderA <=> $orderB;
            }
            return $a['priority'] <=> $b['priority'];
        });

        return array_map(static fn(array $entry): array => $entry['item'], $items);
    }

    /**
     * Retrieves all registered header widgets, sorted by priority.
     *
     * @return array<int, mixed> Sorted header widgets.
     */
    public function getHeaderWidgets(): array
    {
        $widgets = $this->headerWidgets;

        usort($widgets, static function (array $a, array $b): int {
            return $a['priority'] <=> $b['priority'];
        });

        return array_map(static fn(array $entry): mixed => $entry['widget'], $widgets);
    }

    /**
     * Safely executes a single composer callback or class instance.
     *
     * @param callable|string $composer Callback or class name.
     * @param string $view Target view name.
     * @param array<string, mixed> $data Current data state.
     * @return array<string, mixed> Mutated or augmented data.
     */
    private function executeComposer(callable|string $composer, string $view, array $data): array
    {
        try {
            if (is_callable($composer)) {
                $result = $composer($data, $view);
                return is_array($result) ? array_merge($data, $result) : $data;
            }

            if (is_string($composer) && class_exists($composer)) {
                $instance = new $composer();
                if (method_exists($instance, 'compose')) {
                    $result = $instance->compose($data, $view);
                    return is_array($result) ? array_merge($data, $result) : $data;
                }
            }
        } catch (\Throwable $e) {
            error_log("ViewComposerRegistry Error on view [{$view}]: " . $e->getMessage());
        }

        return $data;
    }

    /**
     * Resets all registered composers, widgets, and shared data.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->composers = [];
        $this->sidebarItems = [];
        $this->headerWidgets = [];
        $this->sharedData = [];
    }
}
