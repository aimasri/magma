<?php

declare(strict_types=1);

namespace Magma\view;

/**
 * Title: Local File View Loader
 *
 * Purpose:
 * - Implements ViewLoaderInterface for local filesystem template loading.
 * - Supports namespaced template resolution (e.g. 'Services::index', 'Menu::partials/card').
 * - Utilizes in-memory caching to eliminate redundant disk I/O during heavy rendering loops.
 *
 * Why / Why this design:
 * - In-Memory Path Caching: Rendering hundreds of partials or nested sub-templates in a single request
 *   can degrade performance due to repeated file_exists() and realpath() system calls. Caching
 *   resolved paths reduces filesystem overhead to O(1) in-memory lookups.
 * - Modular Monolith Decoupling: Namespaced template resolution decouples domain modules from the
 *   application root view directory, allowing modules to bundle their own private views.
 *
 * Teaching notes:
 * - Templates can be referenced using standard paths ('welcome', 'layouts/default') or namespaced
 *   syntax ('<Namespace>::<view/path>'). The .php extension is automatically appended if omitted.
 */
class LocalFileViewLoader implements ViewLoaderInterface
{
    /** @var string Base directory for un-namespaced root application views. */
    private string $basePath;

    /** @var array<string, string> Registered namespaces mapped to absolute directory paths. */
    private array $namespaces = [];

    /** @var array<string, string> In-memory cache of resolved absolute file paths. */
    private array $pathCache = [];

    /** @var array<string, bool> In-memory cache of file existence status. */
    private array $existsCache = [];

    /**
     * Initializes the local file view loader with a base views directory.
     *
     * Execution Flow:
     * 1. Normalize the base path with a trailing directory separator.
     * 2. Store the normalized path for standard template resolution.
     *
     * @param string $basePath Absolute root directory for application views.
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = !empty($basePath)
            ? rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR
            : '';
    }

    /**
     * Registers a root directory path for a specific view namespace.
     *
     * Execution Flow:
     * 1. Trim whitespace and normalize trailing directory separators.
     * 2. Store the path in the $namespaces registry.
     * 3. Invalidate path cache entries relating to this namespace.
     *
     * @param string $namespace The namespace identifier (e.g., 'Services', 'Menu').
     * @param string $path The absolute directory path.
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $normalizedNamespace = trim($namespace, '\\/');
        $normalizedPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;

        $this->namespaces[$normalizedNamespace] = $normalizedPath;

        // Clear cache for namespaced lookups
        $this->pathCache = [];
        $this->existsCache = [];
    }

    /**
     * Checks if a specific namespace has been registered.
     *
     * @param string $namespace The namespace identifier.
     * @return bool True if registered, false otherwise.
     */
    public function hasNamespace(string $namespace): bool
    {
        $normalizedNamespace = trim($namespace, '\\/');
        return isset($this->namespaces[$normalizedNamespace]);
    }

    /**
     * Retrieves all registered namespaces and their bound directory paths.
     *
     * @return array<string, string> Associative array of [namespace => path].
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Resolves a template name into an absolute filesystem path.
     *
     * Execution Flow:
     * 1. Check if the path has already been resolved in memory ($pathCache). If so, return it.
     * 2. Detect if the template contains a namespace delimiter ('::').
     * 3. If namespaced:
     *    a. Split into namespace and relative template path.
     *    b. Look up registered directory path in $namespaces.
     *    c. Throw RuntimeException if namespace is not registered.
     *    d. Construct the full absolute path.
     * 4. If standard:
     *    a. Prepend $basePath to the relative template path.
     * 5. Ensure the file has a .php extension.
     * 6. Verify file existence on disk; throw RuntimeException if not found.
     * 7. Cache the resolved path and return it.
     *
     * @param string $template The template name (e.g. 'welcome', 'Menu::item_card').
     * @return string Absolute filesystem path to the PHP view file.
     * @throws \RuntimeException If the template or namespace cannot be resolved.
     */
    public function resolvePath(string $template): string
    {
        if (isset($this->pathCache[$template])) {
            return $this->pathCache[$template];
        }

        $cleanTemplate = ltrim($template, '/\\');

        if (str_contains($cleanTemplate, '::')) {
            $parts = explode('::', $cleanTemplate, 2);
            $namespace = trim($parts[0], '\\/');
            $subPath = ltrim($parts[1], '/\\');

            if (!isset($this->namespaces[$namespace])) {
                throw new \RuntimeException(
                    "View namespace [{$namespace}] is not registered. Registered namespaces: " . 
                    implode(', ', array_keys($this->namespaces))
                );
            }
            
            $boundaryPath = $this->namespaces[$namespace];
            $resolvedFile = $boundaryPath . str_replace('/', DIRECTORY_SEPARATOR, $subPath);
        } else {
            $boundaryPath = $this->basePath;
            $resolvedFile = $boundaryPath . str_replace('/', DIRECTORY_SEPARATOR, $cleanTemplate);
        }

        if (!str_ends_with($resolvedFile, '.php')) {
            $resolvedFile .= '.php';
        }

        $realBoundary = realpath($boundaryPath);
        $realResolved = realpath($resolvedFile);

        if ($realResolved !== false && $realBoundary !== false && !str_starts_with($realResolved, $realBoundary)) {
            throw new \RuntimeException("Path traversal detected in view resolution.");
        }

        if (!file_exists($resolvedFile)) {
            $this->existsCache[$template] = false;
            throw new \RuntimeException("View template file not found: {$resolvedFile} (referenced as '{$template}')");
        }

        $this->existsCache[$template] = true;
        $this->pathCache[$template] = $resolvedFile;

        return $resolvedFile;
    }

    /**
     * Checks whether a template file exists on disk.
     *
     * Execution Flow:
     * 1. Check in-memory existence cache.
     * 2. If not cached, attempt to resolve path without throwing exceptions.
     * 3. Return boolean existence.
     *
     * @param string $template The template identifier.
     * @return bool True if template exists, false otherwise.
     */
    public function exists(string $template): bool
    {
        if (isset($this->existsCache[$template])) {
            return $this->existsCache[$template];
        }

        try {
            $this->resolvePath($template);
            return true;
        } catch (\Throwable) {
            $this->existsCache[$template] = false;
            return false;
        }
    }

    /**
     * Loads and returns the raw content of a template file.
     *
     * @param string $template The template identifier.
     * @return string The raw template file content.
     * @throws \RuntimeException If the template cannot be loaded.
     */
    public function load(string $template): string
    {
        $filePath = $this->resolvePath($template);
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read view template file: {$filePath}");
        }

        return $content;
    }
}
