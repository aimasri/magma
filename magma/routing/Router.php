<?php

namespace Magma\routing;

use Magma\container\Container;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\http\HttpResponseException;
use Magma\middleware\MiddlewareResolver;

use Magma\http\RequestInterface;

/**
 * Application Routing Engine
 *
 * Purpose:
 * - Map incoming HTTP requests to corresponding controller actions or closures.
 * - Compile human-friendly path templates (e.g. `/orders/{id}`) into regex patterns.
 * - Merge global and route-specific middleware and dispatch them through a unified Pipeline.
 *
 * Why / Why this design:
 * - Implements the Front Controller routing pattern. Centralizing all URL-to-action mapping 
 *   in a single configuration array (`routes.php`) makes the application structure transparent 
 *   and allows the framework to inject route-specific middleware before the controller runs.
 *
 * Teaching notes:
 * - The router evaluates routes sequentially, which operates in O(n) time. In a massive 
 *   application with thousands of routes, this sequential evaluation becomes a bottleneck, 
 *   and a compiled "Trie-based" or cached router (like FastRoute) is required.
 */
class Router implements RouterInterface
{
    private Container $container;
    private MiddlewareResolver $middlewareResolver;
    private array $routes;
    private array $staticRoutes;
    private array $compiledMegaRegexes;

    public function __construct(Container $container, MiddlewareResolver $middlewareResolver, array $routes)
    {
        $this->container = $container;
        $this->middlewareResolver = $middlewareResolver;
        
        $this->routes = [];
        $this->staticRoutes = [];
        foreach ($routes as $route) {
            $method = $route[0];
            $path = $route[1];
            
            if (!str_contains($path, '{')) {
                $this->staticRoutes[$method][$path] = $route;
            } else {
                $this->routes[$method][] = $route;
            }
        }

        $this->compiledMegaRegexes = [];
        foreach ($this->routes as $method => $routes) {
            $regexes = [];
            foreach ($routes as $index => $route) {
                $path = $route[1];
                $constraints = $route[3] ?? [];
                
                $pattern = preg_quote($path, '#');
                $pattern = preg_replace_callback('/\\\{([a-zA-Z0-9_]+)\\\}/', function ($matches) use ($constraints) {
                    $name = $matches[1];
                    $regex = $constraints[$name] ?? '[^/]+';
                    return "(?P<$name>$regex)";
                }, $pattern);
                
                $regexes[] = $pattern . '(*MARK:' . $index . ')';
            }
            $this->compiledMegaRegexes[$method] = '#^(?:' . implode('|', $regexes) . ')$#';
        }
    }

    /**
     * Dispatches the Request to the appropriate handler.
     * 
     * Execution Flow:
     * 1. Iterate sequentially through all defined routes.
     * 2. Skip any route where the HTTP method doesn't match the request.
     * 3. Compile the defined path into a PCRE regex pattern.
     * 4. If the path matches, extract the URL parameters as named capture groups.
     * 5. Validate the extracted parameters against any custom regex constraints.
     * 6. Pass the matched handler, parameters, and middleware to `executeHandler()`.
     * 
     * Logic behind the logic:
     * - The `preg_replace_callback` inside `compilePattern` transforms `{id}` into 
     *   `(?P<id>[^/]+)`, allowing us to extract the parameter name and its value 
     *   directly from the regex match array.
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
     * Attempts to match the current request against statically defined routes.
     *
     * Execution Flow:
     * 1. Performs an O(1) array hash lookup.
     * 2. If a match is found, wraps and executes the handler.
     *
     * @param string $requestMethod HTTP verb.
     * @param string $requestPath Request URI path.
     * @param RequestInterface $request The incoming HTTP Request.
     * @param array $globalMiddleware Global middleware to apply.
     * @return Response|null Returns a Response on success, null if no match.
     */
    private function matchStaticRoute(string $requestMethod, string $requestPath, RequestInterface $request, array $globalMiddleware): ?Response
    {
        if (isset($this->staticRoutes[$requestMethod][$requestPath])) {
            $route = $this->staticRoutes[$requestMethod][$requestPath];
            $handler = $route[2];
            $routeMiddleware = $route[5] ?? [];
            return $this->executeHandler($handler, [], $routeMiddleware, $request, $globalMiddleware);
        }
        return null;
    }

    /**
     * Attempts to match the current request against dynamically defined routes (containing regex).
     *
     * Execution Flow:
     * 1. Iterates over routes mapped to the specific HTTP method.
     * 2. Compiles the route's path syntax into a PCRE regular expression.
     * 3. Tests the path and extracts any captured parameters.
     * 4. Validates parameters against specific custom constraints.
     * 5. If fully valid, wraps and executes the handler.
     *
     * @param string $requestMethod HTTP verb.
     * @param string $requestPath Request URI path.
     * @param RequestInterface $request The incoming HTTP Request.
     * @param array $globalMiddleware Global middleware to apply.
     * @return Response|null Returns a Response on success, null if no match.
     */
    private function matchDynamicRoute(string $requestMethod, string $requestPath, RequestInterface $request, array $globalMiddleware): ?Response
    {
        if (!isset($this->compiledMegaRegexes[$requestMethod])) {
            return null;
        }

        $megaRegex = $this->compiledMegaRegexes[$requestMethod];

        if (preg_match($megaRegex, $requestPath, $matches)) {
            if (!isset($matches['MARK'])) {
                return null;
            }

            $routeIndex = (int)$matches['MARK'];
            $route = $this->routes[$requestMethod][$routeIndex];
            
            $handler        = $route[2];
            $constraints    = $route[3] ?? [];
            $redirectOnFail = $route[4] ?? null;
            $routeMiddleware= $route[5] ?? [];

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            unset($params['MARK']);

            foreach ($constraints as $name => $regex) {
                if (isset($params[$name]) && !preg_match('#^' . $regex . '$#', $params[$name])) {
                    if ($redirectOnFail) {
                        return new RedirectResponse($redirectOnFail);
                    }
                    return null;
                }
            }
            
            return $this->executeHandler($handler, $params, $routeMiddleware, $request, $globalMiddleware);
        }

        return null;
    }

    /**
     * Detects if the path exists under a different HTTP method to throw a 405 error instead of a 404.
     *
     * Execution Flow:
     * 1. Checks all static routes for the given path under alternate methods.
     * 2. If no static match, scans dynamic routes to see if the path would match structurally.
     * 3. Throws a MethodNotAllowedException (405) if a match is found.
     *
     * Logic behind the logic:
     * - Differentiating between a 404 (Not Found) and a 405 (Method Not Allowed) is crucial 
     *   for proper RESTful compliance and debugging API endpoints.
     *
     * @param string $requestMethod The original request HTTP method.
     * @param string $requestPath The requested URI path.
     * @throws \Magma\routing\MethodNotAllowedException
     */
    private function handleMethodNotAllowedExceptions(string $requestMethod, string $requestPath): void
    {
        foreach ($this->staticRoutes as $method => $paths) {
            if ($method !== $requestMethod && isset($paths[$requestPath])) {
                throw new \Magma\routing\MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
            }
        }

        foreach ($this->routes as $method => $routes) {
            if ($method === $requestMethod) continue;
            
            foreach ($routes as $route) {
                $path = $route[1];
                $constraints = $route[3] ?? [];
                $structuralPattern = $route[6] ?? self::compilePattern($path, $constraints);
                
                if (preg_match($structuralPattern, $requestPath, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $isValid = true;
                    foreach ($constraints as $name => $regex) {
                        if (isset($params[$name]) && !preg_match('#^' . $regex . '$#', $params[$name])) {
                            $isValid = false;
                            break;
                        }
                    }
                    if ($isValid) {
                        throw new \Magma\routing\MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
                    }
                }
            }
        }
    }
    /**
     * Converts a route path into a PCRE Regular Expression.
     * 
     * It transforms human-readable placeholders like `{id}` into 
     * named capture groups `(?P<id>[^/]+)`. This allows the Router to 
     * extract variables directly from the URL.
     */
    private static array $compiledCache = [];

    public static function compilePattern(string $path, array $constraints = []): string
    {
        $cacheKey = $path . md5(serialize($constraints));
        if (isset(self::$compiledCache[$cacheKey])) {
            return self::$compiledCache[$cacheKey];
        }

        // Escape existing regex characters in the path but ignore braces for placeholders
        $pattern = preg_quote($path, '#');
        
        // Convert \{placeholder\} back into a named capture group using custom regex if provided
        $pattern = preg_replace_callback('/\\\{([a-zA-Z0-9_]+)\\\}/', function ($matches) use ($constraints) {
            $name = $matches[1];
            $regex = $constraints[$name] ?? '[^/]+';
            return "(?P<$name>$regex)";
        }, $pattern);
        
        $compiled = '#^' . $pattern . '$#';
        self::$compiledCache[$cacheKey] = $compiled;
        
        return $compiled;
    }

    private static array $reflectionCache = [];

    /**
     * Executes the matched handler wrapped in a unified middleware pipeline.
     * 
     * Purpose:
     * - Dispatches the fully constructed handler and merged middleware payload through the Pipeline.
     * 
     * Execution Flow:
     * 1. Create a core closure that wraps the actual controller method invocation.
     * 2. Merge the global middleware with the route-specific middleware.
     * 3. Pass the merged array to the `MiddlewareResolver` to obtain instantiated objects.
     * 4. Pass the Request, resolved middleware, and core closure to the `Pipeline`.
     * 
     * Logic behind the logic:
     * - The Router delegates dependency resolution to the `MiddlewareResolver` and 
     *   execution to the `Pipeline` to strictly adhere to the Single Responsibility Principle.
     */
    private function executeHandler(array|callable $handler, array $params, array $middlewareList, RequestInterface $request, array $globalMiddleware = []): Response
    {
        $coreHandler = function (RequestInterface $request) use ($handler, $params): Response {
            try {
                if (is_array($handler)) {
                    [$controllerClass, $action] = $handler;
                    $controller = $this->container->get($controllerClass);
                    
                    $cacheKey = $controllerClass . '@' . $action;
                    if (!isset(self::$reflectionCache[$cacheKey])) {
                        $ref = new \ReflectionMethod($controller, $action);
                        $meta = [];
                        foreach ($ref->getParameters() as $param) {
                            $meta[] = [
                                'name' => $param->getName(),
                                'hasDefault' => $param->isDefaultValueAvailable(),
                                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
                            ];
                        }
                        self::$reflectionCache[$cacheKey] = $meta;
                    }
                    
                    $args = [];
                    foreach (self::$reflectionCache[$cacheKey] as $meta) {
                        $name = $meta['name'];
                        if (array_key_exists($name, $params)) {
                            $args[] = $params[$name];
                        } elseif ($meta['hasDefault']) {
                            $args[] = $meta['default'];
                        } else {
                            $args[] = null;
                        }
                    }
                    return $controller->$action(...$args);
                }
                
                $ref = new \ReflectionFunction($handler);
                $args = [];
                foreach ($ref->getParameters() as $param) {
                    $name = $param->getName();
                    if (array_key_exists($name, $params)) {
                        $args[] = $params[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        $args[] = null;
                    }
                }
                return $handler(...$args);
            } catch (HttpResponseException $e) {
                return $e->getResponse();
            }
        };

        $mergedMiddleware = array_merge($globalMiddleware, $middlewareList);
        $resolvedMiddleware = $this->middlewareResolver->resolveAll($mergedMiddleware);

        return (new \Magma\pipeline\Pipeline())
            ->send($request)
            ->through($resolvedMiddleware)
            ->then($coreHandler);
    }
}