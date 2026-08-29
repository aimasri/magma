<?php

/**
 * Title: Autoloader and Path Constants
 *
 * Purpose:
 * - Defines the global directory constants needed by the application.
 * - Registers a manual PSR-4 compliant autoloader.
 *
 * Why this design:
 * - Manual autoloading removes the dependency on Composer for the core system architecture, allowing full control over how and when classes are instantiated.
 *
 * Teaching notes:
 * - Keeping autoloading logic in its own file ensures that scripts (like CLI tools) 
 *   can bootstrap the autoloader without inadvertently firing HTTP-specific DI bindings.
 */

// The absolute path to the project root. Used for resolving files and templates.
define('ROOT_DIR', dirname(__DIR__, 2));

// The absolute path to the public document root.
define('PUBLIC_DIR', ROOT_DIR . '/www');

/**
 * Custom PSR-4 Autoloader.
 * 
 * Instead of using Composer, this manual implementation demonstrates how 
 * namespaces are mapped to the file system.
 * - 'core\': Framework kernel and shared logic.
 * - 'user\': Customer-facing logic, profile management, and auth services.
 * - 'admin\': Back office administrative logic and dashboard.
 */
spl_autoload_register(static function (string $class) {
    $class = ltrim($class, '\\');

    /**
     * Directory Mapping
     * Prefixes are stripped before appending the relative path to the base directory.
     */
    $map = [
        'Magma\\' => ROOT_DIR . '/magma/',
        'App\\' => ROOT_DIR . '/app/',
        'Modules\\' => ROOT_DIR . '/modules/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
