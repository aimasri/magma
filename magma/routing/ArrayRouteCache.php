<?php

namespace Magma\routing;

/**
 * Title: Array Route Cache
 *
 * Purpose:
 * - Implements RouteCacheInterface using a static array to cache mega-regexes 
 *   during long-running processes (e.g. Swoole/RoadRunner).
 *
 * Why / Why this design:
 * - In-Memory Caching: Provides ultra-fast O(1) access to compiled routes without the I/O overhead of file or Redis-based caches.
 * - State Retention: Exploits PHP's static properties in long-running environments to persist state across asynchronous requests.
 *
 * Teaching notes:
 * - This implementation is useless in traditional PHP-FPM setups, where static state is destroyed at the end of each HTTP request. It shines in daemonized environments.
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
