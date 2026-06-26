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
    private array $middleware = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
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

        } catch (\Magma\routing\RouteNotFoundException $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            try {
                $errorHandler = $this->container->get(ErrorHandlerInterface::class);
                $errorHandler->renderNotFound()->send();
            } catch (\Throwable $fatal) {
                http_response_code(404);
            }
        } catch (\Throwable $e) {
            // Safety measure: discard any partially rendered content (e.g., from a crash inside a view)
            // so we can render a clean error page.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            try {
                $errorHandler = $this->container->get(ErrorHandlerInterface::class);
                $errorHandler->handleException($e, $request ?? null)->send();
            } catch (\Throwable $fatal) {
                http_response_code(500);
            }
        }
    }


}
