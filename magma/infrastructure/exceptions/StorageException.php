<?php

declare(strict_types=1);

namespace Magma\infrastructure\exceptions;

/**
 * Title: Storage Exception
 *
 * Purpose:
 * - Represents a failure during underlying storage operations (e.g., local disk I/O, AWS S3 network failure).
 *
 * Why this design:
 * - Provides a unified infrastructure-level exception for file operations, allowing application services to catch a single `StorageException` regardless of the underlying driver.
 *
 * Teaching notes:
 * - When throwing this exception, always pass the underlying driver exception as the `$previous` parameter for debugging.
 */
class StorageException extends InfrastructureException
{
}
