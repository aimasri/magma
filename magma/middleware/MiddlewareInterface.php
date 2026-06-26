<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;

/**
 * Middleware Contract
 *
 * Purpose:
 * - Define a uniform interface for all middleware components.
 * - Guarantee that every middleware can intercept an incoming `Request` and an outgoing `Response`.
 *
 * Why / Why this design:
 * - Implements the Chain of Responsibility pattern. By ensuring all middleware adhere to 
 *   the exact same signature, the `Application` and `Router` can compose them dynamically 
 *   using functional paradigms (like `array_reduce`) without needing to know what any 
 *   specific middleware actually does.
 *
 * Teaching notes:
 * - This interface uses `callable $next` to represent the next layer in the onion. In a PSR-15 
 *   compliant framework, this is typically represented by a `RequestHandlerInterface` object.
 */
interface MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
