<?php

namespace Magma\middleware;

use Magma\container\Container;

/**
 * Middleware Dependency Resolver
 * 
 * Purpose:
 * - Convert raw middleware definitions (strings or arrays) into instantiated `MiddlewareInterface` objects.
 * 
 * Why / Why this design:
 * - Enforces the Single Responsibility Principle (SRP). Neither the Router nor the Pipeline 
 *   should know how to instantiate classes or interact with the DI Container. The Router uses 
 *   this Resolver to translate route definitions, and the Pipeline only operates on the finished objects.
 * 
 * Teaching notes:
 * - A factory class like this allows the system to easily support new middleware definition 
 *   formats (e.g., closures) in the future without touching the Router or Pipeline code.
 */
class MiddlewareResolver
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves an array of middleware definitions into executable instances.
     * 
     * @param array $middlewareList
     * @return MiddlewareInterface[]
     */
    public function resolveAll(array $middlewareList): array
    {
        return array_map([$this, 'resolve'], $middlewareList);
    }

    /**
     * Resolves a single middleware definition.
     * 
     * @param string|object|array $definition
     * @return MiddlewareInterface
     */
    public function resolve(string|object|array $definition): MiddlewareInterface
    {
        if (is_array($definition)) {
            $class = array_shift($definition);
            return new $class(...$definition);
        }

        if (is_string($definition)) {
            return $this->container->get($definition);
        }

        return $definition;
    }
}
