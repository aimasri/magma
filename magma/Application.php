<?php

declare(strict_types=1);

namespace Magma;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\middleware\MiddlewareInterface;
use Magma\error\ErrorHandlerInterface;
use Magma\routing\RouterInterface;
use Magma\view\TemplateEngine;

/**
 * Title: Application Kernel
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
    /** @var array<int, string|MiddlewareInterface> */
    private array $middleware = [];

    /**
     * Initializes the application kernel.
     *
     * Logic behind the logic:
     * - Injects the dependency container and error handler upfront to ensure the application
     *   kernel has the foundational tools needed to resolve dependencies and gracefully handle
     *   exceptions during the request lifecycle.
     *
     * @param Container $container
     * @param ErrorHandlerInterface $errorHandler
     */
    public function __construct(Container $container, ErrorHandlerInterface $errorHandler)
    {
        $this->container = $container;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Appends a middleware to the execution stack.
     * 
     * Execution Flow:
     * 1. Pushes the provided middleware instance or class name into the array.
     * 
     * Logic behind the logic:
     * - Delaying the actual instantiation of class-string middleware until the router dispatch phase 
     *   conserves memory and CPU cycles during bootstrap for middleware that might not even be hit.
     * 
     * @param string|MiddlewareInterface $middleware The middleware class name or instance.
     */
    public function addMiddleware(string|MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Handles an incoming HTTP request and returns an HTTP Response object without sending it.
     * Ideal for functional testing and CLI simulations.
     *
     * @param RequestInterface $request
     * @return Response
     */
    public function handle(RequestInterface $request): Response
    {
        try {
            $router = $this->container->get(RouterInterface::class);
            assert($router instanceof RouterInterface);
            return $router->dispatch($request, $this->middleware);
        } catch (\Throwable $e) {
            return $this->handleKernelError($e, $request);
        }
    }

    /**
     * Executes the application lifecycle.
     * 
     * Execution Flow:
     * 1. Resolve the primary RequestInterface object from the container.
     * 2. Process the request through handle().
     * 3. Send the HTTP headers and body to the client.
     * 
     * @return void
     */
    public function run(): void
    {
        ob_start();
        $bufferedOutput = '';
        $request = null;
        try {
            $request = $this->container->get(RequestInterface::class);
            assert($request instanceof RequestInterface);
            $response = $this->handle($request);
            $bufferedOutput = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $response = $this->handleKernelError($e, $request instanceof RequestInterface ? $request : null);
        }

        // Send headers and body to the client
        $response->send();
        echo $bufferedOutput;
    }

    /**
     * Handles fatal exceptions occurring at the kernel level.
     * 
     * Execution Flow:
     * 1. Checks if the exception is a RouteNotFoundException.
     * 2. If so, triggers the 404 response handler.
     * 3. Otherwise, delegates to the generic 500 error handler.
     * 4. Includes ultimate fallbacks to `http_response_code` if the error handlers themselves crash.
     * 
     * Logic behind the logic:
     * - The nested try/catches ensure that even if the templating engine is entirely broken, 
     *   the client still receives an appropriate HTTP status code rather than a blank screen (WSOD).
     */
    private function handleKernelError(\Throwable $e, ?RequestInterface $request): Response
    {
        if ($e instanceof \Magma\routing\RouteNotFoundException) {
            try {
                return $this->errorHandler->renderNotFound($request, $e);
            } catch (\Throwable $fatal) {
                return new Response('Not Found', 404);
            }
        }

        try {
            return $this->errorHandler->handleException($e, $request);
        } catch (\Throwable $fatal) {
            return new Response('Internal Server Error', 500);
        }
    }


}
