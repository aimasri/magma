<?php

declare(strict_types=1);

namespace Magma\interfaces;

/**
 * Title: Uploaded File Interface
 *
 * Purpose:
 * - Define a standardized contract for HTTP file uploads.
 * - Enforce strict boundaries between the HTTP layer and domain services.
 *
 * Why / Why this design:
 * - Relying on the raw $_FILES superglobal creates tightly coupled, untestable code.
 * - This interface provides an object-oriented wrapper to safely inspect and move uploaded files.
 */
interface UploadedFileInterface
{
    /**
     * Retrieve the original filename sent by the client.
     */
    public function getClientFilename(): ?string;

    /**
     * Retrieve the media type sent by the client.
     */
    public function getClientMediaType(): ?string;

    /**
     * Retrieve the file size in bytes.
     */
    public function getSize(): ?int;

    /**
     * Retrieve the error associated with the uploaded file.
     */
    public function getError(): int;

    /**
     * Move the uploaded file to a new location.
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \RuntimeException on error.
     */
    public function moveTo(string $targetPath): void;
}
