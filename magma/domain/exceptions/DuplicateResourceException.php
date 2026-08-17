<?php

declare(strict_types=1);

namespace Magma\domain\exceptions;

/**
 * Title: Duplicate Resource Exception
 *
 * Purpose:
 * - Thrown when a unique constraint or domain rule is violated by creating a duplicate entity.
 *
 * Why / Why this design:
 * - Domain Exceptions provide clear, expressive error handling tailored to the business rules.
 *
 * Teaching notes:
 * - Catch this exception in controllers or HTTP handlers to translate into a 409 Conflict.
 */
class DuplicateResourceException extends \RuntimeException
{
}
