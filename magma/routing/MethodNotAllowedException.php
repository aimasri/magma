<?php

namespace Magma\routing;

/**
 * Method Not Allowed Exception
 * 
 * Purpose:
 * - Thrown when a request path matches a registered route but the HTTP method does not.
 * 
 * Why / Why this design:
 * - Distinguishing a 405 error from a 404 error allows the global ErrorHandler 
 *   to respond appropriately (e.g. returning a 405 Method Not Allowed header).
 * 
 * Teaching notes:
 * - Standardizing HTTP exception types ensures that developers can catch them 
 *   or rely on the framework to render the correct error page/API response.
 */
class MethodNotAllowedException extends \RuntimeException
{
    public function __construct(string $message = "Method Not Allowed", int $code = 405, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
