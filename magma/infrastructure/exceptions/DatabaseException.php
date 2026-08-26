<?php

declare(strict_types=1);

namespace Magma\infrastructure\exceptions;

use RuntimeException;

/**
 * Title: Database Exception Boundary
 *
 * Purpose:
 * - Serves as the strict boundary exception for all database infrastructure failures.
 *
 * Why / Why this design:
 * - We cannot allow raw PDOExceptions to cross into the application or HTTP layers, 
 *   as they often contain sensitive DSN strings and credentials. By wrapping them 
 *   in this domain-agnostic DatabaseException, we preserve the error state while 
 *   safely sanitizing the output.
 *
 * Teaching notes:
 * - Always catch PDOException at the Repository boundary and throw this exception 
 *   instead. Provide a sanitized message.
 */
class DatabaseException extends RuntimeException
{
}
