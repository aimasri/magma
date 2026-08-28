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
    /** @var array<string, string> */
    private array $compiledMegaRegexes;
    /** @var array{static: array<string, array<int, string>>, dynamic_regex?: string, dynamic_map?: array<int, array<int, string>>} */
    private array $methodNotAllowedIndex;

    public function __construct(
        RouteCollection $collection,
        RouteDispatcher $dispatcher,
        RouteCacheInterface $cache
    ) {
        $this->collection = $collection;
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;

        $cached = $this->cache->get();
        if ($cached !== null) {
            $this->compiledMegaRegexes = $cached['regexes'];
            /** @var array{static: array<string, array<int, string>>, dynamic_regex?: string, dynamic_map?: array<int, array<int, string>>} $methodNotAllowed */
            $methodNotAllowed = $cached['methodNotAllowed'];
            $this->methodNotAllowedIndex = $methodNotAllowed;
        } else {
            $this->compiledMegaRegexes = RouteCompiler::compileMegaRegexes($this->collection->getDynamicRoutes());
            $this->methodNotAllowedIndex = RouteCompiler::compileMethodNotAllowedIndex($this->collection->getStaticRoutes(), $this->collection->getDynamicRoutes());
            $this->cache->set([
                'regexes' => $this->compiledMegaRegexes,
                'methodNotAllowed' => $this->methodNotAllowedIndex
            ]);
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
     * @param array<int, string> $globalMiddleware
     * @return Response
     * @throws RouteNotFoundException
     * @throws MethodNotAllowedException
     */
    public function dispatch(RequestInterface $request, array $globalMiddleware = []): Response
    {
        if ($response = $this->matchStaticRoute($request, $globalMiddleware)) {
            return $response;
        }

        if ($response = $this->matchDynamicRoute($request, $globalMiddleware)) {
            return $response;
        }

        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();
        $this->handleMethodNotAllowedExceptions($requestMethod, $requestPath);

        $e = new RouteNotFoundException("Route not found for path: {$requestPath}", 404);
        $e->setAvailableRoutes($this->routeCollection->all());
        throw $e;
    }

    /**
     * Matches exact static routes without regular expression overhead.
     *
     * @param RequestInterface $request
     * @param array<int, string> $globalMiddleware
     * @return Response|null
     */
    private function matchStaticRoute(
        RequestInterface $request,
        array $globalMiddleware
    ): ?Response {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();
        $staticRoutes = $this->collection->getStaticRoutes();
        if (isset($staticRoutes[$requestMethod][$requestPath])) {
            $route = $staticRoutes[$requestMethod][$requestPath];
            $handler = $route->getHandler();
            $routeMiddleware = $route->getMiddleware();
            
            return $this->dispatcher->dispatch($handler, [], $routeMiddleware, $request, $globalMiddleware);
        }
        return null;
    }

    /**
     * Matches parameterized dynamic routes using the compiled mega-regex.
     *
     * @param RequestInterface $request
     * @param array<int, string> $globalMiddleware
     * @return Response|null
     */
    private function matchDynamicRoute(
        RequestInterface $request,
        array $globalMiddleware
    ): ?Response {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

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

            $handler = $route->getHandler();
            $constraints = $route->getConstraints();
            $redirectOnFail = $route->getRedirectOnFail();
            $routeMiddleware = $route->getMiddleware();

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
        if (isset($this->methodNotAllowedIndex['static'][$requestPath])) {
            $allowed = $this->methodNotAllowedIndex['static'][$requestPath];
            if (!in_array($requestMethod, $allowed, true)) {
                throw new MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
            }
        }

        $dynamicRegex = $this->methodNotAllowedIndex['dynamic_regex'] ?? null;
        if ($dynamicRegex && preg_match($dynamicRegex, $requestPath, $matches)) {
            if (isset($matches['MARK'])) {
                $mark = (int)$matches['MARK'];
                $allowed = $this->methodNotAllowedIndex['dynamic_map'][$mark] ?? [];
                if (!in_array($requestMethod, $allowed, true)) {
                    throw new MethodNotAllowedException("Method Not Allowed for path: {$requestPath}", 405);
                }
            }
        }
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $constraints
     * @return bool
     */
    private function parametersSatisfyConstraints(array $params, array $constraints): bool
    {
        foreach ($constraints as $name => $regex) {
            if (isset($params[$name]) && !preg_match('#^' . $regex . '$#', (string)$params[$name])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $path
     * @param array<string, string> $constraints
     * @return string
     */
    public static function compilePattern(string $path, array $constraints = []): string
    {
        return RouteCompiler::compilePattern($path, $constraints);
    }
}