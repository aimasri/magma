<?php

namespace Magma\routing;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Router Interface
 *
 * Purpose:
 * - Define a contract for application routing engines.
 *
 * Why / Why this design:
 * - Adheres to the Dependency Inversion Principle, allowing the application kernel 
 *   to depend on an abstraction rather than a concrete routing implementation.
 *
 * Teaching notes:
 * - This allows swapping out the sequential router for a compiled or caching router in the future.
 */
interface RouterInterface
{
    /**
     * Dispatch an HTTP Request to the appropriate handler and return a Response.
     *
     * Execution Flow:
     * 1. Evaluates the Request against defined static or dynamic routes.
     * 2. Extracts path parameters if matching a dynamic pattern.
     * 3. Wraps the handler in the provided global middleware stack.
     *
     * Logic behind the logic:
     * - Returns a predictable Response object, enforcing the HTTP boundary.
     *
     * @param RequestInterface $request The incoming HTTP Request.
     * @param array $globalMiddleware Middleware applied across all routes.
     * @return Response The generated HTTP Response.
     */
    public function dispatch(RequestInterface $request, array $globalMiddleware = []): Response;
}
