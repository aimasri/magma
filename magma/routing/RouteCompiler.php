<?php

namespace Magma\routing;

/**
 * Title: Route Compiler
 *
 * Purpose:
 * - Transforms parameterized string routes (e.g., `/user/{id}`) into valid PCRE regular expressions.
 * - Groups multiple dynamic routes into FastRoute-style "mega-regexes".
 *
 * Why this design:
 * - FastRoute Algorithm: Instead of looping over dynamic routes and running `preg_match` repeatedly, it builds a single regex (per HTTP method). The regex engine executes in C, making it massively faster.
 * - Memory Efficiency: Caches compiled structural patterns in memory to prevent repeated string parsing.
 *
 * Teaching notes:
 * - The `(*MARK:index)` syntax in PCRE allows the regex engine to return the exact index of the sub-pattern that matched, instantly mapping back to the route handler.
 */
class RouteCompiler
{
    private static array $compiledCache = [];

    /**
     * Compiles arrays of dynamic routes into a single mega-regex string per HTTP method.
     *
     * 1. Iterates over HTTP methods and their associated dynamic routes.
     * 2. Replaces `{param}` tokens with regex capture groups, applying any user-defined constraints.
     * 3. Appends a PCRE `(*MARK)` token corresponding to the route's array index.
     * 4. Joins all regexes for a method with an OR `|` operator and wraps in delimiters.
     *
     * Logic behind the logic:
     * - Combining patterns forces the PCRE engine to build a single NFA (Nondeterministic Finite Automaton), evaluating all routes in a single pass.
     *
     * @param array $dynamicRoutes
     * @return array
     */
    public static function compileMegaRegexes(array $dynamicRoutes): array
    {
        $compiledMegaRegexes = [];
        foreach ($dynamicRoutes as $method => $routes) {
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
            $compiledMegaRegexes[$method] = '#^(?J)(?:' . implode('|', $regexes) . ')$#';
        }
        return $compiledMegaRegexes;
    }

    public static function compilePattern(string $path, array $constraints = []): string
    {
        $cacheKey = $path . md5(serialize($constraints));
        if (isset(self::$compiledCache[$cacheKey])) {
            return self::$compiledCache[$cacheKey];
        }

        $pattern = preg_quote($path, '#');
        $pattern = preg_replace_callback('/\\\{([a-zA-Z0-9_]+)\\\}/', function ($matches) use ($constraints) {
            $name = $matches[1];
            $regex = $constraints[$name] ?? '[^/]+';
            return "(?P<$name>$regex)";
        }, $pattern);
        
        $compiled = '#^' . $pattern . '$#';
        self::$compiledCache[$cacheKey] = $compiled;
        
        return $compiled;
    }
}
