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
    private static array $reflectionCache = [];

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
     * @param array|callable|Route $handler
     * @param array $params
     * @param array $middlewareList
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @param Router|null $router
     * @return Response
     */
    public function dispatch(
        mixed $handler,
        array $params,
        array $middlewareList,
        RequestInterface $request,
        array $globalMiddleware = [],
        ?Router $router = null
    ): Response {
        $coreHandler = function (RequestInterface $request) use ($handler, $params, $router): Response {
            if ($router !== null) {
                return $router->executeHandler($handler, $params, $request);
            }

            if ($handler instanceof Route) {
                $handler = $handler->getHandler();
            }

            if (is_array($handler)) {
                [$controllerClass, $action] = $handler;
                $controller = $this->container->get($controllerClass);

                $cacheKey = $controllerClass . '@' . $action;
                if (!isset(self::$reflectionCache[$cacheKey])) {
                    $ref = new \ReflectionMethod($controller, $action);
                    self::$reflectionCache[$cacheKey] = $this->buildReflectionMeta($ref);
                }

                $args = $this->resolveDependencies(self::$reflectionCache[$cacheKey], $params, $request);
                $result = $controller->$action(...$args);
            } elseif ($handler instanceof \Closure || is_callable($handler)) {
                $cacheKey = 'closure_' . spl_object_hash(\Closure::fromCallable($handler));
                if (!isset(self::$reflectionCache[$cacheKey])) {
                    $ref = new \ReflectionFunction(\Closure::fromCallable($handler));
                    self::$reflectionCache[$cacheKey] = $this->buildReflectionMeta($ref);
                }

                $args = $this->resolveDependencies(self::$reflectionCache[$cacheKey], $params, $request);
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
            $pipeline = $this->container->has(Pipeline::class) ? $this->container->get(Pipeline::class) : new Pipeline();
            return $pipeline
                ->send($request)
                ->through($resolvedMiddleware)
                ->then($coreHandler);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    private function buildReflectionMeta(\ReflectionFunctionAbstract $ref): array
    {
        $meta = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $meta[] = [
                'name' => $param->getName(),
                'class' => $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null,
                'hasDefault' => $param->isDefaultValueAvailable(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'allowsNull' => $param->allowsNull(),
            ];
        }
        return $meta;
    }

    private function resolveDependencies(array $cacheMeta, array $params, RequestInterface $request): array
    {
        $args = [];
        foreach ($cacheMeta as $meta) {
            $name = $meta['name'];
            $className = $meta['class'];

            // FormRequest auto-wiring & validation
            if ($className !== null && is_subclass_of($className, FormRequest::class)) {
                $validator = $this->container->has(Validator::class)
                    ? $this->container->get(Validator::class)
                    : new Validator();
                /** @var FormRequest $formRequest */
                $formRequest = new $className($request, $validator);
                $formRequest->validate();
                $args[] = $formRequest;
                continue;
            }

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($className && ($className === RequestInterface::class || is_a($request, $className))) {
                $args[] = $request;
            } elseif ($className && $this->container->has($className)) {
                $args[] = $this->container->get($className);
            } elseif ($meta['hasDefault']) {
                $args[] = $meta['default'];
            } elseif ($meta['allowsNull']) {
                $args[] = null;
            } else {
                throw new \RuntimeException("Unable to resolve dependency '\${$name}' for class '" . ($className ?? 'unknown') . "'.");
            }
        }
        return $args;
    }
}
