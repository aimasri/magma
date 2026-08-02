<?php

namespace Magma\routing;

/**
 * Title: Route Collection Registry
 *
 * Purpose:
 * - Stores and categorizes all application routes.
 * - Segregates static routes (exact matches) from dynamic routes (parameterized matches).
 *
 * Why this design:
 * - Data Structure Optimization: By separating static routes, the Router can do a direct O(1) hash map lookup before ever attempting regex matching.
 * - Immutability Focus: Primarily serves as a simple Data Transfer Object (DTO) containing route definitions, keeping it extremely fast and lightweight.
 *
 * Teaching notes:
 * - The `$rawRoutes` array originates from the config/routes.php file.
 * - This class does not perform validation; it assumes the router configuration is structurally sound.
 */
class RouteCollection
{
    private array $routes = [];
    private array $staticRoutes = [];

    public function __construct(array $rawRoutes)
    {
        foreach ($rawRoutes as $route) {
            $method = $route[0];
            $path = $route[1];
            
            if (!str_contains($path, '{')) {
                $this->staticRoutes[$method][$path] = $route;
            } else {
                $this->routes[$method][] = $route;
            }
        }
    }

    public function getStaticRoutes(): array
    {
        return $this->staticRoutes;
    }

    public function getDynamicRoutes(): array
    {
        return $this->routes;
    }
}
