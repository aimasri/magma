<?php

namespace Magma\services;

use Magma\interfaces\StorageInterface;
use RuntimeException;

/**
 * Title: Local File Storage Service
 *
 * Purpose:
 * - Concrete implementation of StorageInterface for the local file system.
 * - Handles directory creation and path resolution safely.
 *
 * Why this design:
 * - Uses an adapter pattern approach allowing the storage mechanism to be swapped (e.g., to S3) without changing consuming code.
 * - Enforces path restrictions to prevent directory traversal vulnerabilities.
 *
 * Teaching notes:
 * - Compare this to Flysystem in Laravel, which abstracts this even further.
 * - When extending this, ensure path resolution remains secure against malicious inputs.
 */
class LocalFileStorageService implements StorageInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Resolves and validates the full path against the base path.
     *
     * Execution Flow:
     * 1. Append the requested path to the base path.
     * 2. Resolve the real path of the directory.
     * 3. Verify that the resolved path starts with the base path to prevent traversal.
     * 4. Return the fully resolved path.
     *
     * Logic behind the logic:
     * - Security pattern: Preventing Directory Traversal (Path Traversal) by enforcing boundary checks.
     *
     * @param string $path
     * @return string
     * @throws RuntimeException
     */
    private function getFullPath(string $path): string
    {
        $requestedPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        
        $directory = realpath(dirname($requestedPath));
        if ($directory === false) {
            throw new RuntimeException("Invalid path provided: directory does not exist.");
        }
        
        $realPath = $directory . DIRECTORY_SEPARATOR . basename($requestedPath);
        
        if (!str_starts_with($realPath, $this->basePath)) {
            throw new RuntimeException("Invalid path provided: path traversal detected.");
        }
        
        return $realPath;
    }

    /**
     * Writes contents to a file at the specified path.
     *
     * @param string $path
     * @param mixed $contents
     * @return bool
     * @throws RuntimeException
     */
    public function put(string $path, mixed $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    /**
     * Retrieves the contents of a file at the specified path.
     *
     * @param string $path
     * @return string|null
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        
        if (!$this->exists($path)) {
            return null;
        }

        $contents = file_get_contents($fullPath);
        return $contents !== false ? $contents : null;
    }

    /**
     * Checks if a file exists at the specified path.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * Deletes a file at the specified path.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        if ($this->exists($path)) {
            return unlink($this->getFullPath($path));
        }
        return false;
    }
}
