<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\container\Container;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Title: Pluggable Domain Strategy Registry
 *
 * Purpose:
 * - A generic, container-aware Strategy Registry & Factory (`StrategyRegistry<T>`).
 * - Dynamically registers and resolves domain algorithms (e.g. pricing, taxation, margin scoring)
 *   by string key with runtime type validation.
 *
 * Why / Why this design:
 * - Open/Closed Principle (OCP): Eliminates monolithic `switch ($strategy)` statements and nested `if/else`
 *   chains in domain services. New algorithms can be added by registering strategies without changing core code.
 * - Dependency Injection Aware: Strategies registered as class names are lazily resolved via the DI container,
 *   ensuring their constructor dependencies are automatically wired at runtime.
 * - Strict Runtime Validation: Verifies that resolved strategy instances implement the expected interface.
 *
 * Teaching notes:
 * - By parameterizing `$expectedInterface` in the constructor, you can instantiate dedicated typed registries:
 *   `$pricingRegistry = new StrategyRegistry($container, PricingStrategyInterface::class);`
 *
 * @template T of object
 */
class StrategyRegistry
{
    /** @var array<string, class-string<T>|T> */
    private array $strategies = [];

    private Container $container;
    private ?string $expectedInterface;

    /**
     * @param Container $container The dependency injection container.
     * @param class-string<T>|null $expectedInterface Optional interface or class name that all registered strategies must implement.
     */
    public function __construct(Container $container, ?string $expectedInterface = null)
    {
        $this->container = $container;
        $this->expectedInterface = $expectedInterface;
    }

    /**
     * Registers a strategy handler by key name.
     *
     * Execution Flow:
     * 1. Normalize the strategy key to lowercase trimmed string.
     * 2. If an object is passed, immediately validate that it implements the expected interface.
     * 3. If a class string is passed, validate that the class exists and implements the expected interface.
     * 4. Store the registration in the internal map.
     *
     * Logic behind the logic:
     * - Performing structural checks at registration time catches misconfigurations during application bootstrap
     *   rather than failing unexpectedly in production workflows.
     *
     * @param string $key The unique identifier for this strategy (e.g. 'fixed_margin', 'vat_standard').
     * @param class-string<T>|T $strategy Class name string or instantiated strategy object.
     * @return static
     * @throws InvalidArgumentException If the strategy does not adhere to the expected interface.
     */
    public function register(string $key, string|object $strategy): static
    {
        $normalizedKey = strtolower(trim($key));

        if ($this->expectedInterface !== null) {
            if (is_object($strategy)) {
                if (!($strategy instanceof $this->expectedInterface)) {
                    throw new InvalidArgumentException(
                        "Strategy [" . get_class($strategy) . "] must implement expected interface [{$this->expectedInterface}]."
                    );
                }
            } elseif (is_string($strategy)) {
                if (!class_exists($strategy)) {
                    throw new InvalidArgumentException("Strategy class [{$strategy}] does not exist.");
                }
                if (!is_subclass_of($strategy, $this->expectedInterface) && $strategy !== $this->expectedInterface) {
                    throw new InvalidArgumentException(
                        "Strategy class [{$strategy}] must implement expected interface [{$this->expectedInterface}]."
                    );
                }
            }
        }

        $this->strategies[$normalizedKey] = $strategy;
        return $this;
    }

    /**
     * Checks if a strategy is registered for the specified key.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->strategies[strtolower(trim($key))]);
    }

    /**
     * Resolves and returns the strategy instance corresponding to the given key.
     *
     * Execution Flow:
     * 1. Normalize the strategy key.
     * 2. Verify that the key exists in the registered strategies map; throw OutOfBoundsException if missing.
     * 3. If the registered value is already an object instance, return it.
     * 4. If it is a class name string, resolve it dynamically via the DI Container.
     * 5. Perform runtime type check and return the resolved strategy.
     *
     * Logic behind the logic:
     * - Using the container to resolve class strings allows strategies to depend on repositories,
     *   configuration, or other services without the registry needing to know those dependencies.
     *
     * @param string $key
     * @return T
     * @throws OutOfBoundsException If no strategy is registered for the given key.
     * @throws InvalidArgumentException If the resolved strategy does not match the expected interface.
     */
    public function resolve(string $key): object
    {
        $normalizedKey = strtolower(trim($key));

        if (!isset($this->strategies[$normalizedKey])) {
            $available = implode(', ', array_keys($this->strategies));
            throw new OutOfBoundsException(
                "No strategy registered for key [{$key}]. Available strategies: [{$available}]."
            );
        }

        $strategy = $this->strategies[$normalizedKey];

        if (is_object($strategy)) {
            return $strategy;
        }

        /** @var T $instance */
        $instance = $this->container->get($strategy);

        if ($this->expectedInterface !== null && !($instance instanceof $this->expectedInterface)) {
            throw new InvalidArgumentException(
                "Resolved strategy [" . get_class($instance) . "] does not implement [{$this->expectedInterface}]."
            );
        }

        return $instance;
    }

    /**
     * Unregisters a strategy by key.
     *
     * @param string $key
     */
    public function unregister(string $key): void
    {
        unset($this->strategies[strtolower(trim($key))]);
    }

    /**
     * Returns all registered strategy keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->strategies);
    }

    /**
     * Returns the raw map of registered strategies.
     *
     * @return array<string, class-string<T>|T>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
