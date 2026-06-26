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
     * @param int $code The HTTP status code (e.g., 500, 404).
     * @param string $message A safe error message for the end user.
     * @param string|null $trace Optional stack trace included only in debug modes.
     * @return Response The generated error response.
     */
    public function renderError(int $code, string $message, ?string $trace = null): Response;

    /**
     * Wrapper for generating a standard 404 Not Found response.
     */
    public function renderNotFound(): Response;
}
