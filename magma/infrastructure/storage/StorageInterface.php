<?php

declare(strict_types=1);

namespace Magma\infrastructure\storage;

/**
 * Title: Enterprise Storage Service Contract
 *
 * Purpose:
 * - Defines the abstraction contract for file persistence, binary payload storage, and secure media uploads.
 * - Decouples domain services and controllers from physical filesystem operations (`move_uploaded_file`, `mkdir`, `unlink`).
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Enables cloud readiness (e.g., swapping Local disk for AWS S3 / Cloudflare R2) and painless unit test disk mocking.
 * - Security by Contract: Enforces binary MIME verification, extension whitelisting, and cryptographic tokenized naming across all storage drivers.
 *
 * Teaching notes:
 * - Direct PHP filesystem superglobals (`$_FILES`) must never be passed directly into domain entities; they must pass through `storeUploadedFile()` on this interface.
 */
interface StorageInterface
{
    /**
     * Stores raw binary string or stream contents at the specified path.
     *
     * @param string $path Target file path or key
     * @param mixed $contents Binary string data or resource handle
     * @return bool True on success, false on failure
     */
    public function put(string $path, mixed $contents): bool;

    /**
     * Retrieves the contents of a stored file.
     *
     * @param string $path File path or object key
     * @return string|null Raw string contents or null if not found
     */
    public function get(string $path): ?string;

    /**
     * Checks if a file exists at the specified path.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Deletes a file at the specified path.
     *
     * @param string $path
     * @return bool True on success, false on failure
     */
    public function delete(string $path): bool;

    /**
     * Validates and securely stores an uploaded file ($_FILES item).
     *
     * @param array $fileInfo Standard PHP file array (tmp_name, name, size, error, type)
     * @param string $directory Destination folder or key prefix (e.g., 'recipes/photos')
     * @param array|null $allowedExtensions Array of lowercase extensions (default: ['jpg', 'jpeg', 'png', 'webp'])
     * @return string Stored relative file path
     * @throws \RuntimeException On upload failure, MIME mismatch, or security violation
     */
    public function storeUploadedFile(
        array $fileInfo,
        string $directory = 'uploads',
        ?array $allowedExtensions = null
    ): string;

    /**
     * Resolves the public accessible URL for a stored asset.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string;

    /**
     * Inspects and returns the binary MIME type of a stored file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string;

    /**
     * Retrieves the size in bytes of a stored file.
     *
     * @param string $path
     * @return int|null
     */
    public function size(string $path): ?int;
}
