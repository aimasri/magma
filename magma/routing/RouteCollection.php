<?php

declare(strict_types=1);

namespace Magma\routing;

/**
 * Title: Route Collection Registry
 *
 * Purpose:
 * - Stores, categorizes, and indexes all registered application routes.
 * - Segregates static routes (O(1) exact matches) from dynamic parameterized routes.
 * - Maintains an inverted index of named routes for O(1) reverse URL generation.
 *
 * Why / Why this design:
 * - Data Structure Optimization: By partitioning routes into static hash tables, dynamic arrays, and named indices, the Router avoids regex overhead entirely for static paths and provides instant reverse lookup.
 * - Polymorphic Ingestion: Accepts raw legacy tuples, `RouteDefinition` builders, and strongly-typed `Route` value objects, ensuring seamless evolution and backward compatibility.
 *
 * Teaching notes:
 * - In production, this collection is populated during bootstrapping from route definitions or loaded from pre-compiled cache manifests via `bin/cache_routes.php`.
 */
class RouteCollection
{
    /** @var array<string, array<int, Route>> Dynamic routes grouped by HTTP method */
    private array $dynamicRoutes = [];

    /** @var array<string, array<string, Route>> Static routes indexed by [method][path] */
    private array $staticRoutes = [];

    /** @var array<string, Route> Named routes indexed by route name */
    private array $namedRoutes = [];

    /**
     * Initializes the RouteCollection registry.
     *
     * @param array $rawRoutes Array of Route, RouteDefinition, or legacy tuple arrays
     */
    public function __construct(array $rawRoutes = [])
    {
        foreach ($rawRoutes as $routeItem) {
            $this->add($routeItem);
        }
    }

    /**
     * Registers a route into the collection and updates internal indices.
     *
     * Execution Flow:
     * 1. Normalizes the route into a strongly-typed `Route` Value Object.
     * 2. Checks if the route contains dynamic parameter tokens (`{param}`).
     * 3. Partitions into `$staticRoutes` hash map or `$dynamicRoutes` array list.
     * 4. If a route name exists, indexes it in the `$namedRoutes` dictionary.
     *
     * Logic behind the logic:
     * - Partitioning at registration time eliminates per-request classification costs, maximizing HTTP throughput.
     *
     * @param Route|RouteDefinition|array $routeItem
     * @return self
     */
    public function add(Route|RouteDefinition|array $routeItem): self
    {
        $route = match (true) {
            $routeItem instanceof Route => $routeItem,
            $routeItem instanceof RouteDefinition => $routeItem->toRoute(),
            is_array($routeItem) => Route::fromTuple($routeItem),
        };

        $method = $route->getMethod();
        $path = $route->getUri();

        if ($route->isStatic()) {
            $this->staticRoutes[$method][$path] = $route;
        } else {
            $this->dynamicRoutes[$method][] = $route;
        }

        if ($route->getName() !== null) {
            $this->namedRoutes[$route->getName()] = $route;
        }

        return $this;
    }

    /**
     * Returns static routes indexed by HTTP method and URI path.
     *
     * @return array<string, array<string, Route>>
     */
    public function getStaticRoutes(): array
    {
        return $this->staticRoutes;
    }

    /**
     * Returns dynamic routes indexed by HTTP method and numerical position.
     *
     * @return array<string, array<int, Route>>
     */
    public function getDynamicRoutes(): array
    {
        return $this->dynamicRoutes;
    }

    /**
     * Retrieves all named routes indexed by unique alias name.
     *
     * @return array<string, Route>
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Finds a route by its unique alias name.
     *
     * @param string $name
     * @return Route|null
     */
    public function getNamedRoute(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Returns a flat array of all registered Route instances.
     *
     * @return Route[]
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->staticRoutes as $routes) {
            foreach ($routes as $route) {
                $all[] = $route;
            }
        }
        foreach ($this->dynamicRoutes as $routes) {
            foreach ($routes as $route) {
                $all[] = $route;
            }
        }
        return $all;
    }
}
