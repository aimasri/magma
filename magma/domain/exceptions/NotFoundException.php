<?php

declare(strict_types=1);

namespace Magma\domain\exceptions;

/**
 * Title: Not Found Exception
 *
 * Purpose:
 * - Thrown when a requested domain entity or resource cannot be located.
 *
 * Why / Why this design:
 * - Domain Exceptions provide clear, expressive error handling tailored to the business rules.
 *
 * Teaching notes:
 * - Catch this exception in controllers or HTTP handlers to translate into a 404 Not Found.
 */
class NotFoundException extends \RuntimeException
{
}
