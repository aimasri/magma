<?php

declare(strict_types=1);

namespace Magma\domain\exceptions;

/**
 * Title: Authorization Exception
 *
 * Purpose:
 * - Thrown when a user or process attempts an action they lack permission to perform.
 *
 * Why / Why this design:
 * - Differentiates authentication (who you are) from authorization (what you can do) within domain rules.
 *
 * Teaching notes:
 * - Catch this exception in controllers or HTTP handlers to translate into a 403 Forbidden.
 */
class AuthorizationException extends \RuntimeException
{
}
