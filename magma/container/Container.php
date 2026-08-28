<?php

declare(strict_types=1);

namespace Magma\container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionException;
use RuntimeException;

/**
 * Title: Dependency Resolver & Service Registry
 *
 * Purpose:
 * - Manage the instantiation, auto-wiring, and lifecycle of application services.
 * - Auto-wire constructor dependencies dynamically using Reflection API inspection.
 * - Cache singleton instances per request and support dynamic instantiation with runtime arguments (`makeWithArgs`).
 *
 * Why / Why this design:
 * - Implements the Inversion of Control (IoC) and Dependency Injection (DI) Container pattern.
 * - Completely decouples object creation and dependency resolution from business and domain logic.
 * - Enables strict adherence to the Dependency Inversion Principle (DIP) and Open/Closed Principle (OCP).
 *
 * Teaching notes:
 * - Reflection auto-wiring has CPU overhead; this container mitigates overhead by caching constructor 
 *   parameter metadata in `$reflectionCache`.
 * - Autoloader delegation in `has()` ensures that uninstantiated PSR-4 classes and interfaces are discovered 
 *   reliably without requiring manual static pre-registration.
 */
class Container
{
    /**
     * Explicitly registered factory closures.
     * @var array<string, callable>
     */
    private array $definitions = [];

    /**
     * Singleton instances cached for the lifecycle of the container.
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Stack tracker to detect circular dependency loops during resolution.
     * @var array<string, bool>
     */
    private array $resolving = [];

    /**
     * In-memory cache for constructor reflection parameter metadata.
     * @var array<string, array<int, array{class?: string, default?: mixed}>>
     */
    private static array $reflectionCache = [];

    /**
     * In-memory cache for class and interface existence checks.
     * @var array<string, bool>
     */
    private static array $classExistsCache = [];

    /**
     * Interface and alias mappings to concrete implementations.
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Registers a manual factory definition for a service identifier.
     *
     * Execution Flow:
     * 1. Store the factory closure in the definitions registry keyed by $id.
     *
     * Logic behind the logic:
     * - Allows complex services requiring custom constructor arguments, PDO handles, or environment
     *   configuration to be lazily instantiated via factory callbacks rather than auto-wiring.
     *
     * @param string $id The service identifier or interface name.
     * @param callable $concrete The factory closure to instantiate the service.
     */
    public function set(string $id, callable $concrete): void
    {
        $this->definitions[$id] = $concrete;
    }

    /**
     * Binds an interface or alias name to a concrete class implementation.
     *
     * Execution Flow:
     * 1. Store the alias mapping in the aliases array.
     *
     * Logic behind the logic:
     * - Fundamental to the Dependency Inversion Principle; enables controllers and services to type-hint
     *   abstract interfaces while the container resolves the bound concrete class.
     *
     * @param string $alias The interface or alias identifier.
     * @param string $concrete The fully-qualified concrete class name.
     */
    public function bind(string $alias, string $concrete): void
    {
        $this->aliases[$alias] = $concrete;
    }

    /**
     * Determines if a service, class, or interface can be resolved by the container.
     *
     * Execution Flow:
     * 1. Resolve alias if present.
     * 2. Check if a manual definition or singleton instance exists.
     * 3. Check cached class/interface existence.
     * 4. Invoke class_exists($id, true) and interface_exists($id, true) with autoloader delegation.
     * 5. Cache and return the boolean result.
     *
     * Logic behind the logic:
     * - Enabling autoloader delegation (`true`) ensures PSR-4 autoloadable classes are discovered 
     *   on demand without requiring pre-loading, while caching prevents repeated filesystem disk hits.
     *
     * @param string $id The service or class identifier.
     * @return bool True if resolvable, false otherwise.
     */
    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->definitions[$id]) || isset($this->instances[$id])) {
            return true;
        }

        if (!array_key_exists($id, self::$classExistsCache)) {
            if (count(self::$classExistsCache) >= 1000) {
                unset(self::$classExistsCache[array_key_first(self::$classExistsCache)]);
            }
            self::$classExistsCache[$id] = class_exists($id, true);
        }

        return self::$classExistsCache[$id];
    }

    /**
     * Retrieves a resolved service instance from the container.
     *
     * Execution Flow:
     * 1. Resolve alias mapping if registered.
     * 2. Return cached singleton instance if previously instantiated.
     * 3. If a factory definition exists, guard against circular dependencies, invoke factory, cache singleton, and return.
     * 4. Otherwise, delegate to auto-wiring resolution via resolve().
     *
     * Logic behind the logic:
     * - Singleton caching is applied to explicit definitions. Auto-wired dependencies remain transient 
     *   by default to prevent memory leaks for request-scoped objects (DTOs, Requests, Jobs).
     *
     * @param string $id The service, interface, or class identifier.
     * @return mixed The instantiated service.
     * @throws RuntimeException If circular dependency or resolution error occurs.
     */
    public function get(string $id): mixed
    {
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->definitions[$id])) {
            if (isset($this->resolving[$id])) {
                throw new RuntimeException("Circular dependency detected while resolving [{$id}].");
            }
            $this->resolving[$id] = true;
            try {
                $this->instances[$id] = $this->definitions[$id]($this);
            } finally {
                unset($this->resolving[$id]);
            }
            return $this->instances[$id];
        }

        return $this->resolve($id);
    }

    /**
     * Dynamically instantiates a class combining resolved DI container dependencies with runtime arguments.
     *
     * Execution Flow:
     * 1. Resolve alias if $class is aliased.
     * 2. Inspect target class constructor using Reflection.
     * 3. For each constructor parameter:
     *    a. Check if passed in $args by parameter name or numeric position.
     *    b. If not in $args and typed as a container-resolvable class/interface, resolve via get().
     *    c. If not resolvable and default value exists, use default value.
     *    d. Otherwise throw RuntimeException.
     * 4. Instantiate and return the object instance.
     *
     * Logic behind the logic:
     * - Allows factory patterns, command handlers, and middleware to inject runtime data (e.g. model IDs, 
     *   options, request DTOs) while preserving container auto-wiring for underlying infrastructure services.
     *
     * @param string $class Fully-qualified class name to instantiate.
     * @param array<string|int, mixed> $args Associative or positional runtime arguments.
     * @return object The instantiated class instance.
     * @throws RuntimeException If class does not exist, is not instantiable, or parameter cannot be resolved.
     */
    public function makeWithArgs(string $class, array $args): object
    {
        $class = $this->aliases[$class] ?? $class;

        if (!class_exists($class)) {
            throw new RuntimeException("Target class [{$class}] does not exist.");
        }

        $reflectionClass = new ReflectionClass($class);

        if (!$reflectionClass->isInstantiable()) {
            throw new RuntimeException("Target class [{$class}] is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->buildDependencies($parameters, $class, $args);

        return $reflectionClass->newInstanceArgs($dependencies);
    }

    /**
     * Automatically instantiates a class by recursively resolving its constructor dependencies.
     *
     * Execution Flow:
     * 1. Check for circular dependencies to prevent stack overflows.
     * 2. Inspect constructor parameters and resolve cached metadata.
     * 3. Recursively call get() for type-hinted service dependencies.
     * 4. Return new instance created with resolved dependencies.
     *
     * Logic behind the logic:
     * - Auto-wiring dramatically reduces manual service configuration. Metadata caching ensures 
     *   reflection overhead is paid exactly once per unique class.
     *
     * @param string $id Class name to resolve.
     * @return mixed Instantiated object.
     * @throws RuntimeException If class is unresolvable or circular dependency is detected.
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
                    } elseif (array_key_exists('class', $param)) {
                        $dependencies[] = $this->get($param['class']);
                    }
                }
                return new $id(...$dependencies);
            }

            if (!class_exists($id)) {
                throw new RuntimeException("Target class [{$id}] does not exist.");
            }

            $reflectionClass = new ReflectionClass($id);

            if (!$reflectionClass->isInstantiable()) {
                throw new RuntimeException("Target class [{$id}] is not instantiable.");
            }

            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null) {
                self::$reflectionCache[$id] = [];
                return new $id();
            }

            $parameters = $constructor->getParameters();
            $cacheEntry = [];
            
            $dependencies = $this->buildDependencies($parameters, $id, [], $cacheEntry);

            if (count(self::$reflectionCache) >= 1000) {
                unset(self::$reflectionCache[array_key_first(self::$reflectionCache)]);
            }
            
            self::$reflectionCache[$id] = $cacheEntry;

            return $reflectionClass->newInstanceArgs($dependencies);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * Flushes all cached singleton instances.
     * 
     * Purpose:
     * - Primarily used by background workers to ensure a clean state between job iterations,
     *   preventing tenant data leakage (e.g., TenantContext).
     *
     * @return void
     */
    public function flushInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Resolves the dependencies for a class constructor using reflection.
     *
     * @param \ReflectionParameter[] $parameters
     * @param string $class
     * @param array<int|string, mixed> $args
     * @param array<int, array{class?: string, default?: mixed}>|null $cacheEntry
     * @return array<int, mixed>
     */
    private function buildDependencies(array $parameters, string $class, array $args = [], ?array &$cacheEntry = null): array
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramPos = $parameter->getPosition();
            $type = $parameter->getType();

            if (array_key_exists($paramName, $args)) {
                $dependencies[] = $args[$paramName];
            } elseif (array_key_exists($paramPos, $args)) {
                $dependencies[] = $args[$paramPos];
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $this->has($type->getName())) {
                $typeName = $type->getName();
                $dependencies[] = $this->get($typeName);
                if ($cacheEntry !== null) {
                    $cacheEntry[] = ['class' => $typeName];
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $val = $parameter->getDefaultValue();
                $dependencies[] = $val;
                if ($cacheEntry !== null) {
                    $cacheEntry[] = ['default' => $val];
                }
            } else {
                throw new RuntimeException("Cannot resolve constructor parameter [{$paramName}] for class [{$class}].");
            }
        }
        return $dependencies;
    }
}
