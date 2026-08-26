<?php

declare(strict_types=1);

namespace Magma\infrastructure\exceptions;

use RuntimeException;

/**
 * Title: Infrastructure Exception Boundary
 *
 * Purpose:
 * - Represents a failure in a third-party service, external API, or underlying 
 *   infrastructure component (e.g., SMTP Mailer, external payment gateway).
 *
 * Why / Why this design:
 * - Separating infrastructure failures from domain logic failures (like validation 
 *   or duplicate resource errors) allows the error handler to route, log, and 
 *   alert appropriately (e.g., paging SREs for infrastructure issues).
 *
 * Teaching notes:
 * - Throw this when an external system fails to complete a task, ensuring it 
 *   triggers dead-letter queues (DLQs) in background workers.
 */
class InfrastructureException extends RuntimeException
{
}
