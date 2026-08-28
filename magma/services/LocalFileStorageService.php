<?php

namespace Magma\services;

use Magma\infrastructure\storage\StorageInterface;
use RuntimeException;

/**
 * Title: Local File Storage Service
 *
 * Purpose:
 * - Concrete implementation of StorageInterface for the local file system.
 * - Handles directory creation and path resolution safely.
 *
 * Why / Why this design:
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
        if (str_contains($path, "\0")) {
            throw new RuntimeException("Invalid path provided: null byte detected.");
        }

        $path = str_replace('\\', '/', ltrim($path, '/\\'));
        $parts = explode('/', $path);
        
        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (count($safeParts) > 0) {
                    array_pop($safeParts);
                } else {
                    throw new RuntimeException("Invalid path provided: path traversal detected.");
                }
            } else {
                $safeParts[] = $part;
            }
        }
        
        return $this->basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $safeParts);
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

    /**
     * @param \Magma\interfaces\UploadedFileInterface $file
     * @param string $directory
     * @param array<int, string>|null $allowedExtensions
     */
    public function storeUploadedFile(
        \Magma\interfaces\UploadedFileInterface $file,
        string $directory = 'uploads',
        ?array $allowedExtensions = null
    ): string {
        $allowed = $allowedExtensions ?? ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Invalid upload");
        }
        
        $name = $file->getClientFilename() ?? 'unknown';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            throw new RuntimeException("Extension not allowed");
        }
        
        $token = bin2hex(random_bytes(16));
        $filename = $token . '.' . $ext;
        $path = $directory . '/' . $filename;
        
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $tmpPath = sys_get_temp_dir() . '/' . $filename;
        $file->moveTo($tmpPath);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/csv'];
        if (!in_array($mime, $allowedMimes, true)) {
            unlink($tmpPath);
            throw new RuntimeException("Invalid file content payload. MIME type blocked: {$mime}");
        }

        if (!rename($tmpPath, $fullPath)) {
            unlink($tmpPath);
            throw new RuntimeException("Failed to move file to final destination.");
        }
        
        return $path;
    }

    public function url(string $path): string
    {
        return '/storage/' . ltrim($path, '/');
    }

    public function mimeType(string $path): ?string
    {
        if ($this->exists($path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $this->getFullPath($path));
                finfo_close($finfo);
                return is_string($mime) ? $mime : null;
            }
        }
        return null;
    }

    public function size(string $path): ?int
    {
        if ($this->exists($path)) {
            $size = filesize($this->getFullPath($path));
            return is_int($size) ? $size : null;
        }
        return null;
    }
}
