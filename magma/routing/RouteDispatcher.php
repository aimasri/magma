<?php

namespace Magma\routing;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\HttpResponseException;
use Magma\middleware\MiddlewareResolver;
use Magma\pipeline\Pipeline;

/**
 * Title: Route Dispatcher
 *
 * Purpose:
 * - Instantiates controllers, injects dependencies into handler methods, and executes them.
 * - Wraps handlers in middleware pipelines (global and route-specific).
 *
 * Why this design:
 * - Separation of Concerns: Keeps parameter resolution, DI, and middleware orchestration separate from the regex matching engine.
 * - Reflection Caching: Uses static arrays to cache Reflection metadata, avoiding expensive reflection overhead on repeated requests (especially in worker environments).
 *
 * Teaching notes:
 * - Automatically injects container services into controller actions based on type-hinting.
 * - Any thrown HttpResponseException is elegantly caught and returned as a normal response, simplifying early-exit logic in controllers.
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
     * Executes the matched route handler through a middleware pipeline.
     *
     * 1. Wraps the handler (closure or controller method) inside a core closure.
     * 2. Within the core closure, uses reflection (or cached reflection) to resolve dependencies and route parameters.
     * 3. Merges global and route-specific middleware and resolves their instances.
     * 4. Passes the request through the Pipeline architecture, culminating at the core closure.
     *
     * Logic behind the logic:
     * - Wrapping the controller execution in a closure allows the Pipeline to treat the final destination as just another middleware.
     * - Caching reflection metadata significantly cuts down execution time under high load.
     *
     * @param array|callable $handler
     * @param array $params
     * @param array $middlewareList
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @return Response
     */
    public function dispatch(array|callable $handler, array $params, array $middlewareList, RequestInterface $request, array $globalMiddleware = []): Response
    {
        $coreHandler = function (RequestInterface $request) use ($handler, $params): Response {
            try {
                if (is_array($handler)) {
                    [$controllerClass, $action] = $handler;
                    $controller = $this->container->get($controllerClass);
                    
                    $cacheKey = $controllerClass . '@' . $action;
                    if (!isset(self::$reflectionCache[$cacheKey])) {
                        $ref = new \ReflectionMethod($controller, $action);
                        self::$reflectionCache[$cacheKey] = $this->buildReflectionMeta($ref);
                    }
                    
                    $args = $this->resolveDependencies(self::$reflectionCache[$cacheKey], $params, $request);
                    return $controller->$action(...$args);
                }
                
                $cacheKey = 'closure_' . spl_object_hash($handler);
                if (!isset(self::$reflectionCache[$cacheKey])) {
                    $ref = new \ReflectionFunction($handler);
                    self::$reflectionCache[$cacheKey] = $this->buildReflectionMeta($ref);
                }

                $args = $this->resolveDependencies(self::$reflectionCache[$cacheKey], $params, $request);
                return $handler(...$args);
            } catch (HttpResponseException $e) {
                return $e->getResponse();
            }
        };

        $mergedMiddleware = array_merge($globalMiddleware, $middlewareList);
        $resolvedMiddleware = $this->middlewareResolver->resolveAll($mergedMiddleware);

        return (new Pipeline())
            ->send($request)
            ->through($resolvedMiddleware)
            ->then($coreHandler);
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
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
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

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($className === \Magma\http\RequestInterface::class || $className === \Magma\http\Request::class) {
                $args[] = $request;
            } elseif ($className && $this->container->has($className)) {
                $args[] = $this->container->get($className);
            } elseif ($meta['hasDefault']) {
                $args[] = $meta['default'];
            } else {
                $args[] = null;
            }
        }
        return $args;
    }
}
