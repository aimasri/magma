<?php

declare(strict_types=1);

namespace Magma\routing;

/**
 * Title: Dynamic Route Discovery Engine
 *
 * Purpose:
 * - Dynamically scans, loads, and merges routes from core, application, and module directories.
 * - Prevents downstream application route pollution in the core framework configuration.
 *
 * Why / Why this design:
 * - SRP: Encapsulates the logic of discovering and merging routes across the project structure.
 * - OCP: Allows adding new modules without modifying the core route loader.
 * - Deterministic Scanning: Uses scandir over glob for more robust cross-platform compliance.
 *
 * Teaching notes:
 * - This engine is utilized both in development (real-time routing) and production builds (cache generation).
 */
class RouteDiscoveryEngine
{
    /**
     * Discovers and merges all routes across the application.
     *
     * Execution Flow:
     * 1. Loads Core Framework routes.
     * 2. Loads Downstream Application routes.
     * 3. Deterministically scans the modules directory to load module-specific routes.
     *
     * @return array<int, mixed>
     */
    public static function discoverRoutes(): array
    {
        $routes = [];
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);

        // 1. Core Framework Routes
        $coreRoutesFile = $rootDir . '/magma/config/routes.php';
        if (file_exists($coreRoutesFile)) {
            $coreRoutes = require $coreRoutesFile;
            if (is_array($coreRoutes)) {
                $routes = array_merge($routes, $coreRoutes);
            }
        }

        // 2. Downstream Application Routes
        $appRoutesFile = $rootDir . '/app/routes.php';
        if (file_exists($appRoutesFile)) {
            $appRoutes = require $appRoutesFile;
            if (is_array($appRoutes)) {
                $routes = array_merge($routes, $appRoutes);
            }
        }

        // 3. Module Routes
        $modulesDir = $rootDir . '/modules';
        if (is_dir($modulesDir)) {
            $items = scandir($modulesDir);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $moduleRouteFile = $modulesDir . '/' . $item . '/routes.php';
                    if (is_dir($modulesDir . '/' . $item) && file_exists($moduleRouteFile)) {
                        $moduleRoutes = require $moduleRouteFile;
                        if (is_array($moduleRoutes)) {
                            $routes = array_merge($routes, $moduleRoutes);
                        }
                    }
                }
            }
        }

        return $routes;
    }
}
