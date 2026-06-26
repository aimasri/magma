<?php

namespace Magma\view;

/**
 * File View Loader
 *
 * Purpose:
 * - Implement ViewLoaderInterface to load templates from the local filesystem.
 *
 * Why / Why this design:
 * - Encapsulates filesystem operations, making it easier to mock or replace view loading.
 *
 * Teaching notes:
 * - Uses output buffering and local variable scope to safely render PHP template files.
 */
class FileViewLoader implements ViewLoaderInterface
{
    /**
     * Checks if the template file exists on the local filesystem.
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Loads and evaluates the PHP template file.
     *
     * Execution Flow:
     * 1. Starts an output buffer to prevent premature output.
     * 2. Evaluates the file using `require`, allowing it access to `$data`.
     * 3. Captures and returns the buffered output.
     * 4. Ensures buffers are closed safely if an exception occurs during rendering.
     *
     * Logic behind the logic:
     * - The try/catch block is critical here. Without it, a fatal error in a view 
     *   would leave the output buffer open, leading to partial HTML responses.
     */
    public function load(string $path, array $data): string
    {
        ob_start();
        try {
            require $path;
            return ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            throw $e;
        }
    }
}
