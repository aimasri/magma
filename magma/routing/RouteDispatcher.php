<?php

declare(strict_types=1);

namespace Magma\routing;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\HttpResponseException;
use Magma\middleware\MiddlewareResolver;
use Magma\pipeline\Pipeline;
use Magma\validation\FormRequest;
use Magma\validation\Validator;

/**
 * Title: Route Dispatcher & Pipeline Middleware Runner
 *
 * Purpose:
 * - Executes matched route handlers inside the application middleware pipeline.
 * - Manages reflection parameter resolution, FormRequest validation, and DI container auto-wiring.
 * - Handles early HTTP exceptions (`HttpResponseException`) cleanly without leaking errors.
 *
 * Why / Why this design:
 * - Separation of Concerns: Keeps middleware execution and reflection auto-wiring cleanly decoupled from regex URI matching in the Router.
 * - Onion Middleware Architecture: Wraps controller execution as the innermost core closure of the Pipeline, ensuring all outward/inward middleware filters execute symmetrically.
 *
 * Teaching notes:
 * - When an action requires a `FormRequest`, validation is automatically fired before the controller action starts.
 */
class RouteDispatcher
{
    private Container $container;
    private MiddlewareResolver $middlewareResolver;

    public function __construct(Container $container, MiddlewareResolver $middlewareResolver)
    {
        $this->container = $container;
        $this->middlewareResolver = $middlewareResolver;
    }

    /**
     * Executes the matched route handler through the middleware onion.
     *
     * Execution Flow:
     * 1. Wraps the handler inside a core closure.
     * 2. Resolves handler parameters with reflection auto-wiring and FormRequest validation.
     * 3. Resolves and merges global and route-specific middleware instances.
     * 4. Passes the request through the Pipeline architecture, hitting the core closure.
     * 5. Catches any `HttpResponseException` and returns its internal Response.
     *
     * Logic behind the logic:
     * - Treating the controller execution as the innermost destination closure allows uniform middleware composition.
     *
     * @param array<int, string>|callable|string|Route $handler
     * @param array<string, string> $params
     * @param array<int, string> $middlewareList
     * @param RequestInterface $request
     * @param array<int, string> $globalMiddleware
     * @return Response
     */
    public function dispatch(
        mixed $handler,
        array $params,
        array $middlewareList,
        RequestInterface $request,
        array $globalMiddleware = []
    ): Response {
        $coreHandler = function (RequestInterface $request) use ($handler, $params): Response {
            if ($handler instanceof Route) {
                $handler = $handler->getHandler();
            }

            $resolver = new RouteParameterResolver($this->container);

            if (is_array($handler)) {
                $controllerClass = $handler[0] ?? null;
                $action = $handler[1] ?? null;

                if (!is_string($controllerClass) || !is_string($action)) {
                    throw new \InvalidArgumentException('Invalid array handler: must be [string, string].');
                }

                $controller = $this->container->get($controllerClass);

                if (!is_object($controller)) {
                    throw new \InvalidArgumentException(sprintf('Controller "%s" must be an object.', $controllerClass));
                }

                $args = $resolver->resolveDependencies(new \ReflectionMethod($controller, $action), $params, $request);
                $result = $controller->$action(...$args);
            } elseif ($handler instanceof \Closure || is_callable($handler)) {
                $ref = new \ReflectionFunction(\Closure::fromCallable($handler));
                $args = $resolver->resolveDependencies($ref, $params, $request);
                $result = $handler(...$args);
            } else {
                throw new \InvalidArgumentException('Invalid route handler.');
            }

            if ($result instanceof Response) {
                return $result;
            }

            if (is_array($result) || is_object($result)) {
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
            }

            return new Response((string)$result, 200);
        };

        $mergedMiddleware = array_merge($globalMiddleware, $middlewareList);

        $resolvedMiddleware = $this->middlewareResolver->resolveAll($mergedMiddleware);

        try {
            /** @var Pipeline $pipeline */
            $pipeline = $this->container->has(Pipeline::class) ? $this->container->get(Pipeline::class) : new Pipeline();
            $response = $pipeline
                ->send($request)
                ->through($resolvedMiddleware)
                ->then($coreHandler);
                
            if (!$response instanceof Response) {
                throw new \RuntimeException('Pipeline must return a valid Response instance.');
            }
            
            return $response;
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }
}
