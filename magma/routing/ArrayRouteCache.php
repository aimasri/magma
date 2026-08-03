<?php

namespace Magma\routing;

/**
 * Array Route Cache
 *
 * Purpose:
 * - Implements RouteCacheInterface using a static array to cache mega-regexes 
 *   during long-running processes (e.g. Swoole/RoadRunner).
 */
class ArrayRouteCache implements RouteCacheInterface
{
    private static array $cache = [];

    public function set(array $regexes): void
    {
        self::$cache = $regexes;
    }

    public function get(): ?array
    {
        return !empty(self::$cache) ? self::$cache : null;
    }
}
