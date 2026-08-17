<?php

namespace Magma\routing;

/**
 * Title: Route Cache Interface
 *
 * Purpose:
 * - Abstracts the storage and retrieval of compiled mega-regexes for routing.
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
