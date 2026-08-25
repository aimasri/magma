<?php

declare(strict_types=1);

namespace Magma\interfaces;

/**
 * Title: Uploaded File Interface
 *
 * Purpose:
 * - Define a standardized contract for HTTP file uploads
 * - Enforce strict boundaries between the HTTP layer and domain services
 *
 * Why / Why this design:
 * - Relying on the raw $_FILES superglobal creates tightly coupled, untestable code
 * - This interface provides an object-oriented wrapper to safely inspect and move uploaded files
 *
 * Teaching notes:
 * - Mocking this interface allows controllers to be tested without performing actual filesystem writes
 */
interface UploadedFileInterface
{
    /**
     * Retrieve the original filename sent by the client.
     *
     * 1. Inspects the original file name provided during the HTTP upload.
     *
     * @return string|null The original filename, or null if none was provided.
     */
    public function getClientFilename(): ?string;

    /**
     * Retrieve the media type sent by the client.
     *
     * 1. Inspects the MIME type of the uploaded file as declared by the client.
     *
     * @return string|null The media type, or null if none was provided.
     */
    public function getClientMediaType(): ?string;

    /**
     * Retrieve the file size in bytes.
     *
     * 1. Retrieves the size of the uploaded file as reported by the system.
     *
     * @return int|null The file size in bytes, or null if unknown.
     */
    public function getSize(): ?int;

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * 1. Maps the raw upload error code (e.g., UPLOAD_ERR_OK) to an integer.
     *
     * @return int One of the PHP UPLOAD_ERR_XXX constants.
     */
    public function getError(): int;

    /**
     * Move the uploaded file to a new location.
     *
     * 1. Verifies the file is a valid upload.
     * 2. Safely moves the file to the target destination path.
     * 3. Throws an exception if the file cannot be moved or if there are permissions issues.
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \RuntimeException on error.
     */
    public function moveTo(string $targetPath): void;
}
