<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use RuntimeException;

/**
 * Title: PSR-15 Middleware Adapter
 *
 * Purpose:
 * - Adapt standard PSR-15 Middleware (`Psr\Http\Server\MiddlewareInterface`) into Magma's native 
 *   middleware pipeline (`Magma\middleware\MiddlewareInterface`).
 * - Bridge the interface gap between standard PSR-15 `process(ServerRequestInterface, RequestHandlerInterface)` 
 *   and Magma's functional `$next` closure pipeline signature.
 *
 * Why / Why this design:
 * - Implements the Adapter (Wrapper) Pattern adhering to the Open/Closed Principle (OCP).
 * - Allows developers to seamlessly reuse ecosystem PSR-15 middleware (e.g., CORS, security headers, 
 *   session handlers, authentication guards) without rewriting them for Magma or polluting the core kernel.
 *
 * Teaching notes:
 * - In PSR-15, the next middleware is represented as a `RequestHandlerInterface` object whose `handle()` 
 *   method is invoked. This adapter creates an on-the-fly request handler that bridges to Magma's `$next` callable.
 */
class Psr15Adapter implements MiddlewareInterface
{
    /**
     * The wrapped PSR-15 middleware instance.
     */
    private object $psrMiddleware;

    /**
     * Initializes the PSR-15 Middleware Adapter.
     *
     * @param object $psrMiddleware The PSR-15 middleware instance implementing a `process()` method.
     * @throws RuntimeException If the provided object does not implement a `process()` method.
     */
    public function __construct(object $psrMiddleware)
    {
        if (!method_exists($psrMiddleware, 'process')) {
            $class = get_class($psrMiddleware);
            throw new RuntimeException("Provided object [{$class}] does not implement a PSR-15 compatible 'process()' method.");
        }

        $this->psrMiddleware = $psrMiddleware;
    }

    /**
     * Process an incoming server request through the adapted PSR-15 middleware.
     *
     * Execution Flow:
     * 1. Create an anonymous RequestHandler wrapper that encapsulates the `$next` callable.
     * 2. Call `process()` on the underlying PSR-15 middleware instance, passing the request and handler.
     * 3. Validate the returned result and convert it to a Magma `Response` instance if necessary.
     * 4. Return the finalized `Response`.
     *
     * Logic behind the logic:
     * - The anonymous handler class fulfills the PSR-15 `RequestHandlerInterface` structural contract, 
     *   allowing the third-party middleware to delegate back into Magma's onion pipeline naturally.
     *
     * @param Request $request The incoming Magma HTTP Request.
     * @param callable $next The next middleware layer in the pipeline.
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Construct an anonymous request handler wrapping the callable $next.
        $handler = new class($next) {
            /** @var callable */
            private $next;

            public function __construct(callable $next)
            {
                $this->next = $next;
            }

            public function handle(mixed $request): mixed
            {
                return ($this->next)($request);
            }
        };

        /** @var callable $callable */
        $callable = [$this->psrMiddleware, 'process'];
        $result = $callable($request, $handler);

        if ($result instanceof Response) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'getStatusCode') && method_exists($result, 'getBody')) {
            $statusCode = (int) $result->getStatusCode();
            $body = (string) $result->getBody();
            $headers = method_exists($result, 'getHeaders') ? $result->getHeaders() : [];
            
            $flattenedHeaders = [];
            foreach ($headers as $name => $values) {
                $flattenedHeaders[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
            }

            return new Response($body, $statusCode, $flattenedHeaders);
        }

        if (is_string($result)) {
            return new Response($result, 200);
        }

        throw new RuntimeException("PSR-15 middleware [". get_class($this->psrMiddleware) . "] returned an invalid response type.");
    }

    /**
     * Get the underlying PSR-15 middleware instance.
     *
     * @return object
     */
    public function getUnderlyingMiddleware(): object
    {
        return $this->psrMiddleware;
    }
}
