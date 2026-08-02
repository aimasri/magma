<?php

namespace Magma\error;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Error Handler Interface
 *
 * Purpose:
 * - Define a contract for application-level exception normalization.
 *
 * Why / Why this design:
 * - Adheres to the Dependency Inversion Principle, allowing the application kernel to rely 
 *   on an abstraction for error handling.
 *
 * Teaching notes:
 * - This allows replacing the standard HTML error handler with a JSON/API-specific handler.
 */
interface ErrorHandlerInterface
{
    /**
     * Intercepts and handles an uncaught Throwable.
     *
     * Execution Flow:
     * 1. Categorizes the exception to determine the appropriate HTTP status code.
     * 2. Logs the failure securely without exposing internals.
     * 3. Renders a user-facing HTTP Response representing the error.
     *
     * Logic behind the logic:
     * - The `$request` is optional because exceptions can occur before the Request is fully resolved.
     *
     * @param \Throwable $e The thrown exception.
     * @param RequestInterface|null $request The incoming request, if available.
     * @return Response The generated error response.
     */
    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response;

    /**
     * Renders a specific HTTP error response.
     *
     * Execution Flow:
     * 1. Constructs an appropriate payload containing the message and optional trace.
     * 2. Defers to a templating engine or raw string builder to generate the HTTP body.
     * 3. Returns an immutable Response object encapsulating the payload and status code.
     *
     * Logic behind the logic:
     * - Decoupling the rendering phase from exception handling allows you to reuse this method 
     *   for manual aborts (like `abort(403)`) even when an exception wasn't thrown.
     *
     * @param int $code The HTTP status code (e.g., 500, 404).
     * @param string $message A safe error message for the end user.
     * @param string|null $trace Optional stack trace included only in debug modes.
     * @return Response The generated error response.
     */
    public function renderError(int $code, string $message, ?string $trace = null): Response;

    /**
     * Wrapper for generating a standard 404 Not Found response.
     *
     * Execution Flow:
     * 1. Purges output buffers to ensure a clean slate.
     * 2. Calls renderError with a fixed 404 code and user-friendly message.
     *
     * Logic behind the logic:
     * - This wrapper prevents magic numbers and duplicated strings throughout the framework 
     *   for the most common HTTP error.
     *
     * @return Response
     */
    public function renderNotFound(): Response;
}
