<?php

namespace Magma\view;

/**
 * View Loader Interface
 *
 * Purpose:
 * - Define a contract for loading and checking the existence of view templates.
 *
 * Why / Why this design:
 * - Decouples the TemplateEngine from the local filesystem, improving testability 
 *   and allowing views to be loaded from alternate sources (e.g., database, memory).
 *
 * Teaching notes:
 * - This adheres to the Single Responsibility Principle by moving file resolution out of the engine.
 */
interface ViewLoaderInterface
{
    /**
     * Verifies if a template file exists at the specified path.
     *
     * @param string $path The absolute path to the view template.
     * @return bool True if the file exists and is readable.
     */
    public function exists(string $path): bool;

    /**
     * Loads the template file, passing the provided data into its symbol table.
     *
     * Execution Flow:
     * 1. Extracts the `$data` array so variables are accessible in the template scope.
     * 2. Evaluates the template and returns the resulting content as a string.
     *
     * @param string $path The absolute path to the view template.
     * @param array $data The variables to expose within the template.
     * @return string The fully rendered HTML template string.
     */
    public function load(string $path, array $data): string;
}
