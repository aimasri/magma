<?php

declare(strict_types=1);

namespace Magma\view;

/**
 * Title: View Loader Interface
 *
 * Purpose:
 * - Defines the contract for resolving and loading template view files from storage/filesystem.
 * - Supports namespaced template references (e.g., 'Services::index', 'Menu::item_card').
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Decouples the TemplateEngine from the physical filesystem.
 * - Modular Monolith Architecture: Enables modular packages/domains to register their own view
 *   namespaces without requiring fragile relative path traversals (e.g., '../../modules/Services/views').
 * - High-Performance Caching: Allows implementation classes to cache path existence checks in memory.
 *
 * Teaching notes:
 * - Implementations must handle both standard relative template paths ('welcome', 'layouts/default')
 *   and namespaced template paths ('Module::view_name').
 */
interface ViewLoaderInterface
{
    /**
     * Registers a root directory path for a specific view namespace.
     *
     * Execution Flow:
     * 1. Receive the namespace identifier (e.g., 'Services', 'Menu') and absolute directory path.
     * 2. Normalize and bind the path to the internal namespace registry.
     *
     * @param string $namespace The namespace identifier (e.g., 'Services', 'Admin').
     * @param string $path The absolute directory path containing the view templates.
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void;

    /**
     * Checks if a specific namespace has been registered.
     *
     * @param string $namespace The namespace identifier.
     * @return bool True if registered, false otherwise.
     */
    public function hasNamespace(string $namespace): bool;

    /**
     * Retrieves all registered namespaces and their bound directory paths.
     *
     * @return array<string, string> Associative array of [namespace => path].
     */
    public function getNamespaces(): array;

    /**
     * Resolves a template name into an absolute filesystem path.
     *
     * Execution Flow:
     * 1. Parse template identifier for namespace delimiter (e.g., '::').
     * 2. If namespaced, lookup registered directory and append relative view path.
     * 3. If standard, append relative view path to default base views directory.
     * 4. Return the fully qualified filesystem path (with .php extension).
     *
     * @param string $template The template name (e.g., 'welcome' or 'Menu::item_card').
     * @return string The absolute resolved filesystem path.
     * @throws \RuntimeException If the template path cannot be resolved or file does not exist.
     */
    public function resolvePath(string $template): string;

    /**
     * Checks whether a template file exists on disk.
     *
     * Execution Flow:
     * 1. Attempt to resolve template path via cache/registry.
     * 2. Return true if file exists, false otherwise.
     *
     * @param string $template The template name to verify.
     * @return bool True if template exists and is readable, false otherwise.
     */
    public function exists(string $template): bool;

    /**
     * Loads and returns the raw content of a template file.
     *
     * @param string $template The template name.
     * @return string The raw content of the template file.
     * @throws \RuntimeException If the template cannot be found or read.
     */
    public function load(string $template): string;
}
