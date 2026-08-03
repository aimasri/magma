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
     * Execution Flow:
     * 1. Takes an array of mixed middleware definitions.
     * 2. Iterates over the array mapping each definition through the `resolve()` method.
     * 3. Returns a uniformly typed array of instantiated MiddlewareInterface objects.
     * 
     * Logic behind the logic:
     * Utilizing `array_map` provides a functional approach to transform configuration metadata into operational domain objects without managing stateful loops.
     * 
     * @param array $middlewareList
     * @return MiddlewareInterface[]
     */
    public function resolveAll(array $middlewareList): array
    {
        return array_map([$this, 'resolve'], $middlewareList);
    }

    /**
     * Resolves a single middleware definition into an instantiated object.
     * 
     * Execution Flow:
     * 1. Inspects the type of the incoming definition.
     * 2. If it's an array, it treats the first element as the class name and applies the remaining elements as constructor arguments via the spread operator.
     * 3. If it's a string, it defers instantiation to the Dependency Injection Container, allowing for auto-wiring of dependencies.
     * 4. If it's already an object, it assumes it's a pre-configured instance and returns it directly.
     * 
     * Logic behind the logic:
     * This method acts as a polymorphic factory, abstracting away the instantiation complexity from the router, allowing developers to define middleware using the most convenient syntax for their use-case.
     * 
     * @param string|object|array $definition
     * @return MiddlewareInterface
     */
    public function resolve(string|object|array $definition): MiddlewareInterface
    {
        if (is_array($definition)) {
            $class = array_shift($definition);
            $instance = $this->container->get($class);
            if (method_exists($instance, 'configure')) {
                $instance->configure(...$definition);
            }
            return $instance;
        }

        if (is_string($definition)) {
            return $this->container->get($definition);
        }

        return $definition;
    }
}
