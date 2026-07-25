<?php

namespace Magma\container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionException;
use RuntimeException;

/**
 * Dependency Resolver & Service Registry
 *
 * Purpose:
 * - Manage the instantiation and lifecycle of application services.
 * - Auto-wire dependencies by inspecting constructor type-hints.
 * - Cache instantiated services to ensure singleton behavior per request.
 *
 * Why / Why this design:
 * - Implements the Dependency Injection (DI) Container pattern. This keeps object creation 
 *   out of business logic, ensuring classes are loosely coupled, highly testable, and adhere 
 *   to the Inversion of Control principle.
 *
 * Teaching notes:
 * - Auto-wiring is convenient but relies on Reflection, which has CPU overhead. We mitigate this 
 *   using an in-memory `$reflectionCache` to prevent redundant ReflectionAPI calls within the same 
 *   request lifecycle or across long-lived CLI worker jobs.
 * - In a massive production environment, this container could still be replaced by a compiled PSR-11 
 *   container (like PHP-DI) that caches factory closures directly to disk for absolute zero overhead.
 */
class Container
{
    private array $definitions = [];
    private array $instances = [];
    private array $resolving = [];
    private static array $reflectionCache = [];
    private static array $classExistsCache = [];
    private array $aliases = [];

    /**
     * Manually registers a service definition.
     */
    public function set(string $id, callable $concrete): void
    {
        $this->definitions[$id] = $concrete;
    }

    /**
     * Binds an interface or alias to a concrete class.
     */
    public function bind(string $alias, string $concrete): void
    {
        $this->aliases[$alias] = $concrete;
    }

    /**
     * Determines if a service can be resolved.
     * 
     * Logic behind the logic:
     * - We cache the result of `class_exists()` to prevent the PHP SPL autoloader 
     *   from repeatedly scanning the filesystem for non-existent classes.
     */
    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->definitions[$id])) {
            return true;
        }

        if (!array_key_exists($id, self::$classExistsCache)) {
            if (class_exists($id, false)) {
                self::$classExistsCache[$id] = true;
            } else {
                return false;
            }
        }

        return self::$classExistsCache[$id];
    }

    /**
     * Retrieves a service instance.
     * 
     * If the service hasn't been created yet, it will be instantiated 
     * (and cached) using either a definition or auto-wiring logic.
     */
    public function get(string $id): mixed
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->definitions[$id])) {
            $this->instances[$id] = $this->definitions[$id]($this);
            return $this->instances[$id];
        }

        // Auto-wired dependencies are transient by default, so we DO NOT cache them in $this->instances.
        // This prevents memory leaks for per-request objects like Requests, Jobs, and DTOs.
        return $this->resolve($id);
    }

    /**
     * Automatically instantiates a class by resolving its dependencies.
     * 
     * Execution Flow:
     * 1. Check for circular dependencies to prevent infinite loops.
     * 2. Use `ReflectionClass` to inspect the target class constructor.
     * 3. Iterate over constructor parameters, recursively calling `get()` for typed dependencies.
     * 4. Instantiate the class with the resolved dependencies.
     * 
     * Logic behind the logic:
     * - "Auto-wiring" drastically reduces boilerplate configuration. However, we use a `resolving` 
     *   array to track active instantiations. Without this, if Class A depends on Class B, and 
     *   Class B depends on Class A, the container would crash with a memory exhaustion error.
     */
    private function resolve(string $id): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new RuntimeException("Circular dependency detected while resolving [{$id}].");
        }

        $this->resolving[$id] = true;

        try {
            if (isset(self::$reflectionCache[$id])) {
                $dependencies = [];
                foreach (self::$reflectionCache[$id] as $param) {
                    if (array_key_exists('default', $param)) {
                        $dependencies[] = $param['default'];
                    } else {
                        $dependencies[] = $this->get($param['class']);
                    }
                }
                return new $id(...$dependencies);
            }

            try {
                $reflectionClass = new ReflectionClass($id);
            } catch (ReflectionException $e) {
                throw new RuntimeException("Target class [{$id}] does not exist.", 0, $e);
            }

            if (!$reflectionClass->isInstantiable()) {
                throw new RuntimeException("Target class [{$id}] is not instantiable.");
            }

            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null) {
                self::$reflectionCache[$id] = [];
                return new $id();
            }

            $parameters = $constructor->getParameters();
            $dependencies = [];
            $cacheEntry = [];

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $val = $parameter->getDefaultValue();
                        $dependencies[] = $val;
                        $cacheEntry[] = ['default' => $val];
                        continue;
                    }
                    throw new RuntimeException("Cannot resolve parameter [{$parameter->getName()}] in class [{$id}].");
                }

                $typeName = $type->getName();
                $dependencies[] = $this->get($typeName);
                $cacheEntry[] = ['class' => $typeName];
            }

            self::$reflectionCache[$id] = $cacheEntry;

            return $reflectionClass->newInstanceArgs($dependencies);
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
