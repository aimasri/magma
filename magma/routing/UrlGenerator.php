<?php

declare(strict_types=1);

namespace Magma\routing;

use Magma\http\RequestInterface;

/**
 * Title: Bidirectional URL Generator & Named Route Resolver
 *
 * Purpose:
 * - Provides centralized URL generation with support for named route resolution (`route('items.show', ['id' => 42])`).
 * - Generates secure absolute URLs based on trusted environment configurations, preventing Host Header Injection attacks.
 *
 * Why / Why this design:
 * - Bidirectional Named Routing: Decouples URL path patterns from controllers and view templates. If a path changes from `/users/{id}` to `/members/{id}`, views referencing `route('users.show', ['id' => 5])` update automatically without template refactoring.
 * - Single Source of Truth: Injects `RouteCollection` to resolve route aliases in O(1) time.
 *
 * Teaching notes:
 * - Unused parameters passed to `route()` that are not present as `{token}` placeholders in the route pattern are automatically serialized into standard URL query strings (e.g. `?tab=details&sort=asc`).
 */
class UrlGenerator
{
    protected RequestInterface $request;
    protected string $appUrl;
    protected ?RouteCollection $routes;

    /**
     * Initializes the URL generator with the request context and base URL.
     *
     * @param RequestInterface $request
     * @param string $appUrl
     * @param RouteCollection|null $routes
     */
    public function __construct(RequestInterface $request, string $appUrl, ?RouteCollection $routes = null)
    {
        $this->request = $request;
        $this->appUrl = rtrim($appUrl, '/');
        $this->routes = $routes;
    }

    /**
     * Sets the active RouteCollection for named route resolution.
     *
     * @param RouteCollection $routes
     * @return void
     */
    public function setRouteCollection(RouteCollection $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * Resolves a named route into a fully qualified absolute URL with substituted parameters.
     *
     * Execution Flow:
     * 1. Looks up the `Route` object in the `RouteCollection` by its unique alias name.
     * 2. If the named route does not exist, throws an `\InvalidArgumentException`.
     * 3. Scans the route's URI pattern for `{token}` placeholders and substitutes matching keys from `$params`.
     * 4. Verifies that all required URI placeholders were satisfied.
     * 5. Collects any remaining unused parameters in `$params` and appends them as a query string.
     * 6. Prepends `$this->appUrl` (or returns relative path if `$absolute` is false).
     *
     * Logic behind the logic:
     * - Parameter segregation ensures required path identifiers become RESTful path segments while optional filters become query parameters.
     *
     * @param string $name The unique named route identifier (e.g., 'recipes.show')
     * @param array<string, mixed> $params Associative array of path placeholders and query params
     * @param bool $absolute Whether to return a full URL with scheme and host
     * @return string Fully constructed URL
     * @throws \InvalidArgumentException If route is not registered or required parameter is missing
     */
    public function route(string $name, array $params = [], bool $absolute = true): string
    {
        if ($this->routes === null) {
            throw new \RuntimeException("RouteCollection has not been bound to UrlGenerator.");
        }

        $route = $this->routes->getNamedRoute($name);
        if ($route === null) {
            throw new \InvalidArgumentException("Named route not found: '{$name}'.");
        }

        $uri = $route->getUri();
        $usedParams = [];

        // Substitute {parameter} tokens
        $resolvedUri = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($params, &$usedParams, $name): string {
            $paramName = $matches[1];
            if (!array_key_exists($paramName, $params)) {
                throw new \InvalidArgumentException("Missing required parameter '{$paramName}' for named route '{$name}'.");
            }
            $val = $params[$paramName];
            if (!is_scalar($val) && !$val instanceof \Stringable) {
                throw new \InvalidArgumentException("Parameter '{$paramName}' must be a scalar or Stringable.");
            }
            $usedParams[$paramName] = true;
            return rawurlencode((string)$val);
        }, $uri);

        // Append remaining parameters as query string
        $queryParams = array_diff_key($params, $usedParams);
        if (!empty($queryParams)) {
            $resolvedUri .= (str_contains((string)$resolvedUri, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        if (!$absolute) {
            return (string)$resolvedUri;
        }

        return $this->appUrl . '/' . ltrim((string)$resolvedUri, '/');
    }

    /**
     * Generates an absolute URL for an arbitrary relative path and optional query parameters.
     *
     * Execution Flow:
     * 1. Retrieve the trusted `APP_URL` from the environment configuration.
     * 2. Strip trailing slashes from base URL and leading slashes from path.
     * 3. Concatenate base URL and path.
     * 4. Append encoded query parameters if provided.
     *
     * @param string $path The relative path (e.g., '/reset-password')
     * @param array<string, mixed> $queryParams Optional query parameters to append
     * @return string The fully qualified URL
     */
    public function generateAbsolute(string $path, array $queryParams = []): string
    {
        $url = $this->appUrl . '/' . ltrim($path, '/');

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }
}
