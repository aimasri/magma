<?php

namespace Magma\routing;

/**
 * Title: Route Cache Interface
 *
 * Purpose:
 * - Abstracts the storage and retrieval of compiled mega-regexes for routing.
 * - Provides a unified contract for caching mechanisms (e.g., Array, Redis, File).
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Allows the Router to rely on cached state without hard-coupling to a specific caching driver.
 * - Performance: Prevents recompilation of routing maps on every request in long-lived applications.
 *
 * Teaching notes:
 * - In serverless environments, this can be implemented as an OPcache-friendly file cache to ensure minimal boot time.
 */
interface RouteCacheInterface
{
    /**
     * Store the compiled routes in the cache.
     *
     * @param array $regexes
     * @return void
     */
    public function set(array $regexes): void;

    /**
     * Retrieve the compiled routes from the cache.
     *
     * @return array|null
     */
    public function get(): ?array;
}
