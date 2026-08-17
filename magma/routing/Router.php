<?php

declare(strict_types=1);

namespace Magma\routing;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\http\HttpResponseException;
use Magma\validation\FormRequest;
use Magma\validation\Validator;

/**
 * Title: Application Routing Engine & Parameter Auto-Wiring Dispatcher
 *
 * Purpose:
 * - Maps incoming HTTP requests to corresponding controller actions or closures via Route Value Objects.
 * - Coordinates RouteCollection (registry), RouteCompiler (mega-regex compiler), and RouteDispatcher (middleware onion).
 * - Implements automated reflection dependency injection (`resolveMethodParameters()`) and automated `FormRequest` lifecycle validation before controller invocation.
 *
 * Why / Why this design:
 * - Facade Pattern: Unifies static O(1) hash lookups, dynamic PCRE mega-regex matching, and automated DI parameter resolution behind a clean `dispatch()` API.
 * - Declarative Controller Slimming: Automatically resolving and executing `FormRequest::validate()` prior to controller action execution completely eliminates imperative validation boilerplate from controllers.
 * - Type-Safe Route Handling: Fully refactored to work with strongly-typed `Route` and `RouteDefinition` value objects rather than ambiguous numeric tuples.
 *
 * Teaching notes:
 * - PCRE's `(*MARK:index)` verb maps dynamic URI patterns directly to the Route object array index in O(1) time without sequential regex iterations.
 * - Reflection metadata is memoized statically to ensure zero CPU degradation on high-throughput daemonized execution environments.
 */
class Router implements RouterInterface
{
    private RouteCollection $collection;
    private RouteDispatcher $dispatcher;
    private RouteCacheInterface $cache;
    private ?Container $container;
    private array $compiledMegaRegexes;

    /** @var array<string, array> Reflection parameter metadata cache */
    private static array $reflectionCache = [];

    public function __construct(
        RouteCollection $collection,
        RouteDispatcher $dispatcher,
        RouteCacheInterface $cache,
        ?Container $container = null
    ) {
        $this->collection = $collection;
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
        $this->container = $container;

        $cached = $this->cache->get();
        if ($cached !== null) {
            $this->compiledMegaRegexes = $cached;
        } else {
            $this->compiledMegaRegexes = RouteCompiler::compileMegaRegexes($this->collection->getDynamicRoutes());
            $this->cache->set($this->compiledMegaRegexes);
        }
    }

    /**
     * Resolves an incoming request to an actionable HTTP Response.
     *
     * Execution Flow:
     * 1. Attempts an O(1) hash map lookup for a static route.
     * 2. If missed, evaluates the pre-compiled mega-regex for dynamic routes.
     * 3. On successful dynamic match, validates parameter constraints.
     * 4. If no route matches, checks alternative HTTP methods for 405 Method Not Allowed.
     * 5. If completely unmatched, throws a 404 RouteNotFoundException.
     * 6. Dispatches the handler through the middleware onion and reflection resolver.
     *
     * Logic behind the logic:
     * - Fast static path lookups bypass regex parsing entirely. MethodNotAllowed scans are deferred to the failure path to keep the happy path optimal.
     *
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @return Response
     * @throws RouteNotFoundException
     * @throws MethodNotAllowedException
     */
    public function dispatch(RequestInterface $request, array $globalMiddleware = []): Response
    {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

        if ($response = $this->matchStaticRoute($requestMethod, $requestPath, $request, $globalMiddleware)) {
            return $response;
        }

        if ($response = $this->matchDynamicRoute($requestMethod, $requestPath, $request, $globalMiddleware)) {
            return $response;
        }

        $this->handleMethodNotAllowedExceptions($requestMethod, $requestPath);

        throw new RouteNotFoundException("Route not found for path: {$requestPath}", 404);
    }

    /**
     * Matches exact static routes without regular expression overhead.
     *
     * @param string $requestMethod
     * @param string $requestPath
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @return Response|null
     */
    private function matchStaticRoute(
        string $requestMethod,
        string $requestPath,
        RequestInterface $request,
        array $globalMiddleware
    ): ?Response {
        $staticRoutes = $this->collection->getStaticRoutes();
        if (isset($staticRoutes[$requestMethod][$requestPath])) {
            $route = $staticRoutes[$requestMethod][$requestPath];
            $handler = $route instanceof Route ? $route->getHandler() : $route[2];
            $routeMiddleware = $route instanceof Route ? $route->getMiddleware() : ($route[5] ?? []);
            
            return $this->dispatcher->dispatch($handler, [], $routeMiddleware, $request, $globalMiddleware, $this);
        }
        return null;
    }

    /**
     * Matches parameterized dynamic routes using the compiled mega-regex.
     *
     * @param string $requestMethod
     * @param string $requestPath
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @return Response|null
     */
    private function matchDynamicRoute(
        string $requestMethod,
        string $requestPath,
        RequestInterface $request,
        array $globalMiddleware
    ): ?Response {
        if (!isset($this->compiledMegaRegexes[$requestMethod])) {
            return null;
        }

        $megaRegex = $this->compiledMegaRegexes[$requestMethod];

        if (preg_match($megaRegex, $requestPath, $matches)) {
            if (!isset($matches['MARK'])) {
                return null;
            }

            $routeIndex = (int)$matches['MARK'];
            $routes = $this->collection->getDynamicRoutes();
            $route = $routes[$requestMethod][$routeIndex] ?? null;

            if ($route === null) {
                return null;
            }

            $handler = $route instanceof Route ? $route->getHandler() : $route[2];
            $constraints = $route instanceof Route ? $route->getConstraints() : ($route[3] ?? []);
            $redirectOnFail = $route instanceof Route ? $route->getRedirectOnFail() : ($route[4] ?? null);
            $routeMiddleware = $route instanceof Route ? $route->getMiddleware() : ($route[5] ?? []);

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            unset($params['MARK']);

            if (!$this->parametersSatisfyConstraints($params, $constraints)) {
                if ($redirectOnFail) {
                    return new RedirectResponse($redirectOnFail);
                }
                return null;
            }

            return $this->dispatcher->dispatch($handler, $params, $routeMiddleware, $request, $globalMiddleware, $this);
        }

        return null;
    }

    /**
     * Executes a route handler directly with automated reflection auto-wiring and FormRequest validation.
     *
     * Execution Flow:
     * 1. If handler is [ControllerClass, 'action'], resolves the controller from the container.
     * 2. Inspects method/closure parameters via Reflection (or in-memory reflection cache).
     * 3. Resolves and validates any type-hinted `FormRequest` instances before invocation.
     * 4. Auto-wires route parameters, Request instances, and Container services into the argument list.
     * 5. Invokes the handler and wraps non-Response return values into standard Response objects.
     *
     * Logic behind the logic:
     * - Decoupling parameter resolution and validation into this method ensures controller actions remain strictly declarative.
     *
     * @param Route|array|callable $handler
     * @param array $params
     * @param RequestInterface $request
     * @return Response
     */
    public function executeHandler(Route|array|callable $handler, array $params, RequestInterface $request): Response
    {
        if ($handler instanceof Route) {
            $handler = $handler->getHandler();
        }

        if (is_array($handler)) {
            [$controllerClass, $action] = $handler;
            $controller = $this->container !== null && $this->container->has($controllerClass)
                ? $this->container->get($controllerClass)
                : new $controllerClass();

            $cacheKey = $controllerClass . '@' . $action;
            if (!isset(self::$reflectionCache[$cacheKey])) {
                $ref = new \ReflectionMethod($controller, $action);
                self::$reflectionCache[$cacheKey] = $this->buildReflectionMeta($ref);
            }

            $args = $this->resolveMethodParameters(new \ReflectionMethod($controller, $action), $params, $request);
            $result = $controller->$action(...$args);
        } elseif ($handler instanceof \Closure || is_callable($handler)) {
            $ref = new \ReflectionFunction(\Closure::fromCallable($handler));
            $args = $this->resolveMethodParameters($ref, $params, $request);
            $result = $handler(...$args);
        } else {
            throw new \InvalidArgumentException('Invalid route handler supplied.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        }

        return new Response((string)$result, 200);
    }

    /**
     * Resolves reflection method parameters, auto-wiring FormRequests, route parameters, and container services.
     *
     * Execution Flow:
     * 1. Iterates over each parameter in the method or closure signature.
     * 2. If parameter type is a subclass of `FormRequest`:
     *    a. Instantiates or resolves the `FormRequest` using the current HTTP Request and Validator.
     *    b. Executes `$formRequest->validate()`.
     *    c. Injects the validated `FormRequest` instance.
     * 3. If parameter name matches a captured route parameter (`$id`, `$slug`):
     *    a. Casts the string to target scalar type (int, float, bool, string).
     * 4. If parameter type matches `RequestInterface` or `Request`:
     *    a. Injects the current request instance.
     * 5. If parameter type is registered in the DI Container:
     *    a. Injects the resolved service instance.
     * 6. If parameter has a default value, falls back to the default.
     * 7. If parameter is nullable, falls back to null.
     *
     * Logic behind the logic:
     * - Performing validation inside parameter resolution guarantees that invalid input never touches the controller action body.
     *
     * @param \ReflectionFunctionAbstract $ref
     * @param array $params
     * @param RequestInterface $request
     * @return array
     */
    public function resolveMethodParameters(\ReflectionFunctionAbstract $ref, array $params, RequestInterface $request): array
    {
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $className = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

            // 1. FormRequest Auto-Wiring & Lifecycle Validation
            if ($className !== null && is_subclass_of($className, FormRequest::class)) {
                $validator = $this->container !== null && $this->container->has(Validator::class)
                    ? $this->container->get(Validator::class)
                    : new Validator();

                /** @var FormRequest $formRequest */
                $formRequest = new $className($request, $validator);
                $formRequest->validate();
                $args[] = $formRequest;
                continue;
            }

            // 2. Route Path Parameters ($params[$name])
            if (array_key_exists($name, $params)) {
                $val = $params[$name];
                if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                    $typeName = $type->getName();
                    $val = match ($typeName) {
                        'int' => (int)$val,
                        'float' => (float)$val,
                        'bool' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
                        'string' => (string)$val,
                        default => $val,
                    };
                }
                $args[] = $val;
                continue;
            }

            // 3. Request Interface / Request Instance
            if ($className !== null && ($className === RequestInterface::class || is_a($request, $className))) {
                $args[] = $request;
                continue;
            }

            // 4. DI Container Service Resolution
            if ($className !== null && $this->container !== null && $this->container->has($className)) {
                $args[] = $this->container->get($className);
                continue;
            }

            // 5. Default Parameter Value Fallback
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // 6. Nullable Parameter Fallback
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException(
                "Unable to resolve parameter '\${$name}' for " . ($ref instanceof \ReflectionMethod ? $ref->getDeclaringClass()->getName() . '::' . $ref->getName() : 'closure') . "."
            );
        }

        return $args;
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

    private function handleMethodNotAllowedExceptions(string $requestMethod, string $requestPath): void
    {
        $this->checkStaticRoutesForMethodNotAllowed($requestMethod, $requestPath);
        $this->checkDynamicRoutesForMethodNotAllowed($requestMethod, $requestPath);
    }

    private function checkStaticRoutesForMethodNotAllowed(string $requestMethod, string $requestPath): void
    {
        $staticRoutes = $this->collection->getStaticRoutes();
        foreach ($staticRoutes as $method => $paths) {
            if ($method !== $requestMethod && isset($paths[$requestPath])) {
                throw new MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
            }
        }
    }

    private function checkDynamicRoutesForMethodNotAllowed(string $requestMethod, string $requestPath): void
    {
        foreach ($this->compiledMegaRegexes as $method => $megaRegex) {
            if ($method === $requestMethod) {
                continue;
            }

            if (preg_match($megaRegex, $requestPath, $matches)) {
                if (!isset($matches['MARK'])) {
                    continue;
                }

                $routeIndex = (int)$matches['MARK'];
                $routes = $this->collection->getDynamicRoutes();
                $route = $routes[$method][$routeIndex] ?? null;

                if ($route === null) {
                    continue;
                }

                $constraints = $route instanceof Route ? $route->getConstraints() : ($route[3] ?? []);
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                unset($params['MARK']);

                if ($this->parametersSatisfyConstraints($params, $constraints)) {
                    throw new MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
                }
            }
        }
    }

    private function parametersSatisfyConstraints(array $params, array $constraints): bool
    {
        foreach ($constraints as $name => $regex) {
            if (isset($params[$name]) && !preg_match('#^' . $regex . '$#', (string)$params[$name])) {
                return false;
            }
        }
        return true;
    }

    public static function compilePattern(string $path, array $constraints = []): string
    {
        return RouteCompiler::compilePattern($path, $constraints);
    }
}