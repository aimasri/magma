<?php

namespace Magma\routing;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\RedirectResponse;

/**
 * Title: Application Routing Engine (Facade)
 *
 * Purpose:
 * - Maps incoming HTTP requests to corresponding controller actions or closures.
 * - Coordinates the RouteCollection (registry), RouteCompiler (regex compilation), and RouteDispatcher (handler execution).
 * - Implements fast mega-regex routing optimizations.
 *
 * Why this design:
 * - Facade Pattern: Provides a simplified, high-level API over the complex subsystem of regex compilation and middleware dispatching.
 * - FastRoute Optimization: Consolidating routes into a single mega-regex minimizes the overhead of sequential regex matching, delivering O(1) performance for dynamic route resolution.
 *
 * Teaching notes:
 * - The mega-regex compilation relies on PCRE's `(*MARK)` verb to identify which specific route sub-pattern successfully matched.
 * - Designed for stateless scaling: routing definitions can be cached in worker memory (e.g., Swoole/RoadRunner).
 */
class Router implements RouterInterface
{
    private RouteCollection $collection;
    private RouteDispatcher $dispatcher;
    private array $compiledMegaRegexes;
    private static array $workerCachedRegexes = [];

    public function __construct(RouteCollection $collection, RouteDispatcher $dispatcher)
    {
        $this->collection = $collection;
        $this->dispatcher = $dispatcher;
        
        if (!empty(self::$workerCachedRegexes)) {
            $this->compiledMegaRegexes = self::$workerCachedRegexes;
        } else {
            $this->compiledMegaRegexes = RouteCompiler::compileMegaRegexes($this->collection->getDynamicRoutes());
            self::$workerCachedRegexes = $this->compiledMegaRegexes;
        }
    }

    /**
     * Resolves an incoming request to an actionable response.
     *
     * 1. Attempts an O(1) hash map lookup for a static route.
     * 2. If missed, evaluates the pre-compiled mega-regex for dynamic routes.
     * 3. On successful dynamic match, validates parameter constraints.
     * 4. If no valid match, evaluates HTTP 405 constraints or throws a 404 RouteNotFoundException.
     * 5. Finally, dispatches the handler through global and route middleware.
     *
     * Logic behind the logic:
     * - By trying static routes first, we shortcut regex overhead for exact URI matches. The 405 scan is deferred to the end to optimize the happy path.
     *
     * @param RequestInterface $request
     * @param array $globalMiddleware
     * @return Response
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

    private function matchStaticRoute(string $requestMethod, string $requestPath, RequestInterface $request, array $globalMiddleware): ?Response
    {
        $staticRoutes = $this->collection->getStaticRoutes();
        if (isset($staticRoutes[$requestMethod][$requestPath])) {
            $route = $staticRoutes[$requestMethod][$requestPath];
            $handler = $route[2];
            $routeMiddleware = $route[5] ?? [];
            return $this->dispatcher->dispatch($handler, [], $routeMiddleware, $request, $globalMiddleware);
        }
        return null;
    }

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
            $routes = $this->collection->getDynamicRoutes();
            $route = $routes[$requestMethod][$routeIndex];
            
            $handler        = $route[2];
            $constraints    = $route[3] ?? [];
            $redirectOnFail = $route[4] ?? null;
            $routeMiddleware= $route[5] ?? [];

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            unset($params['MARK']);

            if (!$this->parametersSatisfyConstraints($params, $constraints)) {
                if ($redirectOnFail) {
                    return new RedirectResponse($redirectOnFail);
                }
                return null;
            }
            
            return $this->dispatcher->dispatch($handler, $params, $routeMiddleware, $request, $globalMiddleware);
        }

        return null;
    }

    private function handleMethodNotAllowedExceptions(string $requestMethod, string $requestPath): void
    {
        $staticRoutes = $this->collection->getStaticRoutes();
        foreach ($staticRoutes as $method => $paths) {
            if ($method !== $requestMethod && isset($paths[$requestPath])) {
                throw new \Magma\routing\MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
            }
        }

        $dynamicRoutes = $this->collection->getDynamicRoutes();
        foreach ($dynamicRoutes as $method => $routes) {
            if ($method === $requestMethod) continue;
            
            foreach ($routes as $route) {
                $path = $route[1];
                $constraints = $route[3] ?? [];
                $structuralPattern = $route[6] ?? RouteCompiler::compilePattern($path, $constraints);
                
                if (preg_match($structuralPattern, $requestPath, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    if ($this->parametersSatisfyConstraints($params, $constraints)) {
                        throw new \Magma\routing\MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
                    }
                }
            }
        }
    }

    private function parametersSatisfyConstraints(array $params, array $constraints): bool
    {
        foreach ($constraints as $name => $regex) {
            if (isset($params[$name]) && !preg_match('#^' . $regex . '$#', $params[$name])) {
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