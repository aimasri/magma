<?php

declare(strict_types=1);

namespace Magma\infrastructure\exceptions;

/**
 * Thrown when an underlying storage operation (disk I/O, AWS S3 network) fails.
 */
class StorageException extends InfrastructureException
{
}
