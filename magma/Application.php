<?php

namespace Magma;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\middleware\MiddlewareInterface;
use Magma\error\ErrorHandlerInterface;
use Magma\routing\RouterInterface;
use Magma\view\TemplateEngine;

/**
 * Application Kernel
 *
 * Purpose:
 * - Coordinate the full request lifecycle.
 * - Resolve the primary HTTP Request from the Dependency Container.
 * - Dispatch the request and the global Middleware registry to the Router.
 * - Send the resulting Response to the client.
 *
 * Why / Why this design:
 * - Centralizing orchestration implements the Front Controller pattern. This keeps middleware 
 *   and routing behavior predictable and testable, avoiding scattered global logic and ensuring 
 *   every request follows an identical lifecycle.
 *
 * Teaching notes:
 * - The "onion" middleware pattern used here composes cross-cutting concerns (like CSRF and 
 *   error handling) without polluting controllers. In a production app, you might replace this 
 *   custom kernel with a full PSR-15 Middleware Dispatcher (e.g., Laminas Stratigility).
 */
class Application
{
    private Container $container;
    private ErrorHandlerInterface $errorHandler;
    private array $middleware = [];

    public function __construct(Container $container, ErrorHandlerInterface $errorHandler)
    {
        $this->container = $container;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Appends a middleware to the execution stack.
     * 
     * Middleware can be provided as a class name (to be resolved via DI) or 
     * as a pre-instantiated object. The order of registration determines 
     * the "nesting" of the layers in the handleRequest onion.
     * 
     * @param string|MiddlewareInterface $middleware The middleware class name or instance.
     */
    public function addMiddleware(string|MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Executes the application lifecycle.
     * 
     * Execution Flow:
     * 1. Resolve the primary RequestInterface object from the container.
     * 2. Resolve the RouterInterface from the container.
     * 3. Process the request through the middleware onion via the router.
     * 4. Send the HTTP headers and body to the client.
     * 5. Catch `RouteNotFoundException`, clear any partial output, and render a clean 404 response.
     * 6. Catch any other `Throwable`, clear partial output, and render a 500 error response.
     * 
     * Logic behind the logic:
     * - The try/catch block ensures that even if a fatal error occurs deep within a view 
     *   or a controller, the output buffer is wiped (`ob_end_clean()`) preventing partial 
     *   HTML from bleeding out. It enforces a strict, reliable error boundary for the application.
     */
    public function run(): void
    {
        try {
            // 1. Resolve the primary request object from the container
            $request = $this->container->get(RequestInterface::class);

            // 2. Process the request through the single unified middleware pipeline
            $response = $this->container->get(RouterInterface::class)->dispatch($request, $this->middleware);

            // 3. Send headers and body to the client
            $response->send();

        } catch (\Throwable $e) {
            $this->handleKernelError($e, $request ?? null);
        }
    }

    private function handleKernelError(\Throwable $e, ?RequestInterface $request): void
    {
        if ($e instanceof \Magma\routing\RouteNotFoundException) {
            try {
                $this->errorHandler->renderNotFound()->send();
            } catch (\Throwable $fatal) {
                http_response_code(404);
            }
            return;
        }

        try {
            $this->errorHandler->handleException($e, $request)->send();
        } catch (\Throwable $fatal) {
            http_response_code(500);
        }
    }


}
