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

    /** @var array<string, array<string>> Mapping of allowed extensions to valid MIME types */
    private static array $mimeMap = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'gif'  => ['image/gif'],
        'svg'  => ['image/svg+xml'],
    ];

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

        return file_put_contents($fullPath, $contents) !== false;
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
        return $contents !== false ? $contents : null;
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
            return unlink($this->getFullPath($path));
        }
        return false;
    }

    /**
     * Validates and securely stores an uploaded file ($_FILES payload).
     *
     * Execution Flow:
     * 1. Validates standard PHP upload error codes (`$fileInfo['error'] === UPLOAD_ERR_OK`).
     * 2. Checks file size is greater than 0 and file exists on disk.
     * 3. Extracts original extension and normalizes to lowercase.
     * 4. Verifies extension against strict allowlist (default: `['jpg', 'jpeg', 'png', 'webp']`).
     * 5. Uses native PHP `finfo` (libmagic) to inspect binary header signatures for actual MIME type.
     * 6. Validates that the detected binary MIME matches the permitted MIME types for the extension.
     * 7. Generates a randomized cryptographic 32-character hex token filename (`bin2hex(random_bytes(16))`).
     * 8. Safely moves the uploaded file into the target directory.
     * 9. Returns the clean relative path.
     *
     * Logic behind the logic:
     * - Dual-layer validation (extension check + binary libmagic signature inspection) prevents attackers from bypassing extension checks by uploading `.php` files disguised with image headers or `.jpg` files containing PHP code.
     *
     * @param array $fileInfo Standard PHP file array (tmp_name, name, size, error)
     * @param string $directory Destination folder (e.g., 'recipes/photos')
     * @param array|null $allowedExtensions Allowed extensions allowlist
     * @return string Relative stored path (e.g. 'recipes/photos/a1b2c3d4e5f6...webp')
     * @throws RuntimeException
     */
    public function storeUploadedFile(
        array $fileInfo,
        string $directory = 'uploads',
        ?array $allowedExtensions = null
    ): string {
        $allowed = $allowedExtensions ?? ['jpg', 'jpeg', 'png', 'webp'];
        $allowed = array_map('strtolower', $allowed);

        $errorCode = $fileInfo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload failed with error code [{$errorCode}].");
        }

        $tmpName = $fileInfo['tmp_name'] ?? '';
        if ($tmpName === '' || !file_exists($tmpName) || (int)($fileInfo['size'] ?? 0) <= 0) {
            throw new RuntimeException("Uploaded temporary file is missing or empty.");
        }

        // Extract client extension
        $clientName = (string)($fileInfo['name'] ?? '');
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RuntimeException("Invalid file extension '.{$extension}'. Allowed: " . implode(', ', $allowed));
        }

        // Inspect binary MIME with finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException("Failed to initialize fileinfo buffer.");
        }

        $detectedMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!is_string($detectedMime)) {
            throw new RuntimeException("Unable to inspect file binary MIME signature.");
        }

        $validMimesForExt = self::$mimeMap[$extension] ?? [];
        if (!in_array($detectedMime, $validMimesForExt, true)) {
            throw new RuntimeException(
                "Security violation: Detected binary MIME type '{$detectedMime}' does not match extension '.{$extension}'."
            );
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

        if (is_uploaded_file($tmpName)) {
            if (!move_uploaded_file($tmpName, $fullDestination)) {
                throw new RuntimeException("Failed to move uploaded file to [{$fullDestination}].");
            }
        } else {
            if (!copy($tmpName, $fullDestination)) {
                throw new RuntimeException("Failed to copy temporary file to [{$fullDestination}].");
            }
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
