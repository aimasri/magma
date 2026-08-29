<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Throwable;
use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Debug Error Presenter Interface
 *
 * Purpose:
 * - Defines the contract for presenting errors and exceptions during development or debug mode
 * - Responsible for converting raw Throwable objects into formatted HTTP responses
 *
 * Why / Why this design:
 * - Dependency Inversion and Interface Segregation
 * - Decouples the error handling logic from the final output format (e.g. Whoops, raw HTML, etc.)
 *
 * Teaching notes:
 * - Implementations should ensure that sensitive environment data is not exposed in production
 */
interface DebugErrorPresenterInterface
{
    /**
     * Presents an error or exception as an HTTP response.
     *
     * 1. Evaluates the incoming Throwable and optionally the Request context.
     * 2. Generates an appropriate debug representation of the error.
     * 3. Wraps the result into an HTTP Response object with the given status code.
     *
     * @param Throwable $e The caught exception or error.
     * @param RequestInterface|null $request The HTTP request context (optional).
     * @param int $statusCode The HTTP status code to return (default 500).
     * @return Response
     */
    public function present(Throwable $e, ?RequestInterface $request = null, int $statusCode = 500): Response;

    /**
     * Presents a 404 Not Found error with available routes during debug mode.
     *
     * @param RequestInterface|null $request The HTTP request context (optional).
     * @param array<int|string, mixed> $availableRoutes An array of registered routes to display as suggestions.
     * @param Throwable|null $e The underlying exception (optional).
     * @return Response
     */
    public function presentNotFound(?RequestInterface $request = null, array $availableRoutes = [], ?Throwable $e = null): Response;
}
