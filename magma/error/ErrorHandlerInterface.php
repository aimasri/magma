<?php

declare(strict_types=1);

namespace Magma\error;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Error Handler Contract
 *
 * Purpose:
 * - Defines the contract for application-level exception normalization and content-negotiated error presentation.
 *
 * Why / Why this design:
 * - Adheres to the Dependency Inversion Principle (DIP), allowing the Application Kernel to handle failures without hardcoupling to concrete HTML or JSON presentation drivers.
 *
 * Teaching notes:
 * - Error handlers form the outermost defensive boundary of a web framework.
 */
interface ErrorHandlerInterface
{
    /**
     * Intercepts and handles an uncaught Throwable.
     *
     * @param \Throwable $e The thrown exception or error.
     * @param RequestInterface|null $request The incoming request, if available.
     * @return Response The generated error response.
     */
    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response;

    /**
     * Renders a specific HTTP error response.
     *
     * @param int $code The HTTP status code (e.g., 500, 404, 403).
     * @param string $message A safe error message for the end user.
     * @param string|null $trace Optional stack trace included only in debug modes.
     * @return Response The generated error response.
     */
    public function renderError(int $code, string $message, ?string $trace = null): Response;

    /**
     * Renders a standard 404 Not Found response.
     *
     * @param RequestInterface|null $request Optional request for content-negotiation.
     * @return Response
     */
    public function renderNotFound(?RequestInterface $request = null): Response;
}
