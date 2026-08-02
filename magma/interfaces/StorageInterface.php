<?php

namespace Magma\interfaces;

/**
 * Title: Storage Interface
 *
 * Purpose:
 * - Abstraction for file system operations (Base64 & multipart form payloads).
 *
 * Why this design:
 * - Dependency Inversion Principle (DIP): Removes direct `move_uploaded_file()` and `mkdir()` usage and hardcoded file paths from controllers.
 * - Testing & Portability: Improves unit testability by enabling disk mocking, and paves the way for cloud-native setups (e.g., AWS S3 integration).
 *
 * Teaching notes:
 * - When saving files, always typehint this interface, never concrete local disk classes, to maintain cloud readiness.
 */
interface StorageInterface
{
    /**
     * Stores an uploaded file or raw content.
     *
     * @param string $path The destination path/key.
     * @param mixed $contents The file contents or resource.
     * @return bool True on success, false on failure.
     */
    public function put(string $path, mixed $contents): bool;

    /**
     * Retrieves the contents of a file.
     *
     * @param string $path The file path/key.
     * @return string|null The file contents, or null if not found.
     */
    public function get(string $path): ?string;

    /**
     * Checks if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Deletes a file.
     *
     * @param string $path
     * @return bool True on success, false on failure.
     */
    public function delete(string $path): bool;
}
