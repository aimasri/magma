<?php

declare(strict_types=1);

namespace Magma\routing;

/**
 * Title: Route Compiler Engine
 *
 * Purpose:
 * - Transforms dynamic parameterized route patterns (e.g., `/recipes/{id}`) into valid PCRE regular expressions.
 * - Groups multiple dynamic routes into FastRoute-style consolidated "mega-regexes" with PCRE mark markers.
 *
 * Why / Why this design:
 * - FastRoute Mega-Regex Optimization: Consolidating dynamic routes per HTTP verb into a single regular expression using PCRE's `(*MARK:N)` token allows the underlying C PCRE engine to evaluate routes in a single non-deterministic finite automaton (NFA) pass, cutting time complexity from $O(N)$ sequential regex tests down to $O(1)$.
 * - In-Memory Compilation Caching: Memoizes compiled regex fragments in memory to avoid repeated string tokenization during runtime.
 *
 * Teaching notes:
 * - `(?J)` is the PCRE modifier that permits duplicate named sub-patterns across branches in the mega-regex (e.g. multiple routes having an `{id}` parameter).
 * - `(*MARK:N)` instructs PCRE to set the `$matches['MARK']` value to the integer index of the specific branch that matched.
 */
class RouteCompiler
{
    /** @var array<string, string> In-memory cache of compiled pattern strings */
    private static array $compiledCache = [];

    /**
     * Compiles dynamic routes into a single mega-regex string per HTTP method.
     *
     * Execution Flow:
     * 1. Iterates over HTTP methods in the dynamic route registry.
     * 2. Extracts path URI and regex parameter constraints from each `Route` (or tuple).
     * 3. Replaces parameter tokens (`{param}`) with named regex capture groups.
     * 4. Appends the PCRE `(*MARK:index)` marker to track the matched route index.
     * 5. Joins branch expressions with `|` inside a `(?J)(?: ... )` non-capturing group.
     *
     * Logic behind the logic:
     * - The combined mega-regex forces PCRE to construct a single combined state machine. Matching occurs in C at native CPU speeds without PHP looping overhead.
     *
     * @param array<string, array<int, Route|array<int, mixed>>> $dynamicRoutes
     * @return array<string, string>
     */
    public static function compileMegaRegexes(array $dynamicRoutes): array
    {
        $compiledMegaRegexes = [];
        foreach ($dynamicRoutes as $method => $routes) {
            $regexes = [];
            foreach ($routes as $index => $route) {
                [$path, $constraints] = self::extractRouteDetails($route);
                $pattern = self::replaceTokensWithRegex($path, $constraints);
                $regexes[] = $pattern . '(*MARK:' . $index . ')';
            }

            if (!empty($regexes)) {
                $compiledMegaRegexes[$method] = '#^(?J)(?:' . implode('|', $regexes) . ')$#';
            }
        }
        return $compiledMegaRegexes;
    }

    /**
     * Compiles a single route pattern into a standalone PCRE regex string.
     *
     * @param string $path
     * @param array<string, string> $constraints
     * @return string
     */
    public static function compilePattern(string $path, array $constraints = []): string
    {
        $cacheKey = $path . md5(serialize($constraints));
        if (isset(self::$compiledCache[$cacheKey])) {
            return self::$compiledCache[$cacheKey];
        }

        $pattern = self::replaceTokensWithRegex($path, $constraints);
        $compiled = '#^' . $pattern . '$#';
        self::$compiledCache[$cacheKey] = $compiled;

        return $compiled;
    }

    /**
     * Replaces `{param}` tokens in a path with named regex capture groups.
     *
     * @param string $path
     * @param array<string, string> $constraints
     * @return string
     */
    private static function replaceTokensWithRegex(string $path, array $constraints): string
    {
        $pattern = preg_quote($path, '#');
        return (string)preg_replace_callback('/\\\{([a-zA-Z0-9_]+)\\\}/', function (array $matches) use ($constraints): string {
            $name = $matches[1];
            $regex = $constraints[$name] ?? '[^/]+';
            $regex = str_replace('#', '\#', $regex);
            return "(?P<{$name}>{$regex})";
        }, $pattern);
    }
    /**
     * Compiles an inverted index map for O(1) allowed methods lookup for 405 Method Not Allowed errors.
     *
     * @param array<string, array<string, Route|array<int, mixed>>> $staticRoutes
     * @param array<string, array<int, Route|array<int, mixed>>> $dynamicRoutes
     * @return array{static: array<string, array<int, string>>, dynamic_regex: string, dynamic_map: array<int, array<int, string>>}
     */
    public static function compileMethodNotAllowedIndex(array $staticRoutes, array $dynamicRoutes): array
    {
        $index = [
            'static' => [],
            'dynamic_regex' => '',
            'dynamic_map' => []
        ];

        foreach ($staticRoutes as $method => $routes) {
            foreach ($routes as $path => $route) {
                $index['static'][$path][] = $method;
            }
        }

        $routeMap = [];
        foreach ($dynamicRoutes as $method => $routes) {
            foreach ($routes as $route) {
                [$path, $constraints] = self::extractRouteDetails($route);
                
                $pattern = self::replaceTokensWithRegex($path, $constraints);
                if (!isset($routeMap[$pattern])) {
                    $routeMap[$pattern] = [];
                }
                $routeMap[$pattern][] = $method;
            }
        }

        $regexes = [];
        $markIndex = 0;
        foreach ($routeMap as $pattern => $methods) {
            $regexes[] = $pattern . '(*MARK:' . $markIndex . ')';
            $index['dynamic_map'][$markIndex] = $methods;
            $markIndex++;
        }

        if (!empty($regexes)) {
            $index['dynamic_regex'] = '#^(?J)(?:' . implode('|', $regexes) . ')$#';
        }

        return $index;
    }

    /**
     * Extracts path URI and constraints from a Route object or array tuple.
     *
     * @param Route|array<int, mixed> $route
     * @return array{0: string, 1: array<string, string>}
     */
    private static function extractRouteDetails(mixed $route): array
    {
        if ($route instanceof Route) {
            return [$route->getUri(), $route->getConstraints()];
        }

        $pathVal = $route[1] ?? '/';
        $path = is_scalar($pathVal) || $pathVal instanceof \Stringable ? (string)$pathVal : '/';
        $constraintsRaw = $route[3] ?? [];
        $constraints = is_array($constraintsRaw) ? array_filter($constraintsRaw, 'is_string') : [];

        return [$path, $constraints];
    }
}
