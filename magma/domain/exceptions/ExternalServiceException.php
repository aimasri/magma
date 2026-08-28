<?php

declare(strict_types=1);

namespace Magma\domain\exceptions;

/**
 * Title: External Service Exception
 *
 * Purpose:
 * - Thrown when communication with a third-party API or external service fails.
 *
 * Why / Why this design:
 * - Decouples infrastructure failures of external dependencies from internal database/storage errors.
 *
 * Teaching notes:
 * - Catch this exception in background workers for retry strategies or in HTTP handlers to return a 502 Bad Gateway.
 */
class ExternalServiceException extends \RuntimeException
{
}
