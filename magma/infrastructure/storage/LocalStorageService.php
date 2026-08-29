<?php

declare(strict_types=1);

namespace Magma\infrastructure\storage;

use RuntimeException;

/**
 * Title: Hardened Local Filesystem Storage Service
 *
 * Purpose:
 * - Provides an enterprise-grade local filesystem adapter implementing `StorageInterface`.
 * - Features native `finfo` binary MIME verification, strict extension allowlists (`jpg`, `jpeg`, `png`, `webp`), randomized cryptographic token naming (`bin2hex(random_bytes(16))`), and path traversal defense.
 *
 * Why / Why this design:
 * - Upload Vulnerability Defense: Prohibits relying on client-supplied `$_FILES['type']` or file extensions which attackers can easily spoof to upload PHP web shells. Uses libmagic binary header inspection via `finfo_file()` to verify real payload content.
 * - Cryptographic Token Naming: Generates unguessable 32-character hexadecimal filenames, eliminating file collisions and preventing enumeration attacks across multi-tenant uploads.
 *
 * Teaching notes:
 * - Notice the strict path normalization in `getFullPath()`: it prevents directory traversal attacks (e.g. `../../etc/passwd`) by stripping relative parent sequences.
 */
class LocalStorageService implements StorageInterface
{
    private string $basePath;
    private string $publicBaseUrl;



    /**
     * Initializes the local storage service.
     *
     * Logic behind the logic:
     * - Accepts base paths and standardizes directory separators to prevent mismatches on different operating systems, enforcing a safe initial state.
     *
     * @param string $basePath
     * @param string $publicBaseUrl
     */
    public function __construct(string $basePath, string $publicBaseUrl = '/storage')
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->publicBaseUrl = rtrim($publicBaseUrl, '/');
    }

    /**
     * Stores raw binary string or stream contents at the specified path.
     *
     * @param string $path
     * @param mixed $contents
     * @return bool
     */
    public function put(string $path, mixed $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Storage directory could not be created: [{$directory}].");
            }
        }

        $result = file_put_contents($fullPath, $contents);
        if ($result === false) {
            throw new \Magma\infrastructure\exceptions\StorageException("Failed to write to local storage path: {$fullPath}");
        }
        return true;
    }

    /**
     * Retrieves the string contents of a stored file.
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
        if ($contents === false) {
            throw new \Magma\infrastructure\exceptions\StorageException("Failed to read from local storage path: {$fullPath}");
        }
        return $contents;
    }

    /**
     * Checks if a file exists at the specified relative path.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * Deletes a file at the specified relative path.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        if ($this->exists($path)) {
            $fullPath = $this->getFullPath($path);
            if (!unlink($fullPath)) {
                throw new \Magma\infrastructure\exceptions\StorageException("Failed to delete local storage path: {$fullPath}");
            }
            return true;
        }
        return false;
    }

    /**
     * @param \Magma\interfaces\UploadedFileInterface $file Strongly typed uploaded file object
     * @param string $directory Destination folder (e.g., 'recipes/photos')
     * @param string[]|null $allowedExtensions Allowed extensions allowlist
     * @return string Relative stored path (e.g. 'recipes/photos/a1b2c3d4e5f6...webp')
     * @throws RuntimeException
     */
    public function storeUploadedFile(
        \Magma\interfaces\UploadedFileInterface $file,
        string $directory = 'uploads',
        ?array $allowedExtensions = null
    ): string {
        $allowed = $allowedExtensions ?? ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $allowed = array_map('strtolower', $allowed);

        $errorCode = $file->getError();
        
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload failed with error code [{$errorCode}].");
        }

        $size = $file->getSize() ?? 0;
        
        if ($size <= 0) {
            throw new RuntimeException("Uploaded file is empty.");
        }

        // Extract client extension
        $clientName = $file->getClientFilename() ?? 'unknown';
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RuntimeException("Invalid file extension '.{$extension}'. Allowed: " . implode(', ', $allowed));
        }

        // Generate randomized cryptographic filename
        $token = bin2hex(random_bytes(16));
        $newFilename = "{$token}.{$extension}";

        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        $relativeDestination = $cleanDir !== '' ? "{$cleanDir}/{$newFilename}" : $newFilename;
        $fullDestination = $this->getFullPath($relativeDestination);

        $targetDir = dirname($fullDestination);
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException("Failed to create destination directory: [{$targetDir}].");
            }
        }

        $tmpPath = sys_get_temp_dir() . '/' . $newFilename;
        $file->moveTo($tmpPath);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            unlink($tmpPath);
            throw new RuntimeException("Could not initialize finfo.");
        }
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/csv'];
        if ($mime === false || !in_array($mime, $allowedMimes, true)) {
            unlink($tmpPath);
            throw new RuntimeException("Invalid file content payload. MIME type blocked: {$mime}");
        }

        if (!rename($tmpPath, $fullDestination)) {
            unlink($tmpPath);
            throw new RuntimeException("Failed to move file to final destination.");
        }

        return $relativeDestination;
    }

    /**
     * Resolves the public accessible URL for a stored asset.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string
    {
        return $this->publicBaseUrl . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Inspects and returns the binary MIME type of a stored file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }

    /**
     * Retrieves the size in bytes of a stored file.
     *
     * @param string $path
     * @return int|null
     */
    public function size(string $path): ?int
    {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $size = filesize($fullPath);
        return $size !== false ? $size : null;
    }

    /**
     * Resolves and normalizes an absolute filesystem path, preventing directory traversal.
     *
     * @param string $path
     * @return string
     * @throws RuntimeException
     */
    public function getFullPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException("Invalid path: null byte detected.");
        }

        $normalized = str_replace('\\', '/', ltrim($path, '/\\'));
        $parts = explode('/', $normalized);

        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (count($safeParts) > 0) {
                    array_pop($safeParts);
                } else {
                    throw new RuntimeException("Path traversal attempt detected in: [{$path}].");
                }
            } else {
                $safeParts[] = $part;
            }
        }

        return $this->basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $safeParts);
    }
}
