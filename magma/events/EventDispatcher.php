<?php

declare(strict_types=1);

namespace Magma\events;

use Magma\interfaces\EventDispatcherInterface;
use Magma\interfaces\EventInterface;
use Magma\container\Container;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

/**
 * Title: Strongly-Typed Enterprise Event Dispatcher
 *
 * Purpose:
 * - Manages the registry of domain event listeners and routes dispatched events to registered handlers.
 * - Provides strongly-typed payload resolution for listeners implementing `DomainEventInterface` and `EventPayloadInterface`.
 *
 * Why / Why this design:
 * - Mediator & Pub/Sub Pattern: Decouples domain services triggering business events from observers executing side-effects.
 * - Typed Payload Resolution: Eliminates fragile reflection duck-typing and untyped array casting in listeners
 *   by automatically matching handler parameter typehints (DomainEventInterface vs EventPayloadInterface vs array).
 * - Just-In-Time DI Resolution: Resolves string-based listener classes lazily from the DI container at runtime.
 *
 * Teaching notes:
 * - The dispatcher caches listener reflection parameter types in memory to avoid repeated reflection overhead
 *   during high-throughput event storms.
 */
class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, callable|string|object>> */
    private array $listeners = [];

    /** @var array<string, string|null> Reflection type cache for listener handle() parameters */
    private array $parameterTypeCache = [];

    private Container $container;

    /**
     * Initializes the Event Dispatcher with the dependency injection container.
     *
     * Logic behind the logic:
     * - Injecting the container enables lazy instantiation of event listener classes, significantly reducing memory footprint during request bootstrapping.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Registers a listener for a specific event class name.
     *
     * Execution Flow:
     * 1. Sanitize the event class name.
     * 2. Append the listener (callable, class name string, or object) to the listener registry.
     *
     * Logic behind the logic:
     * - Storing class names as strings permits deferred instantiation via the container until the event
     *   actually fires, reducing memory allocation during bootstrap.
     *
     * @param string $eventName The fully qualified class name of the event.
     * @param callable|string|object $listener The listener callable, class string, or instance.
     */
    public function listen(string $eventName, callable|string|object $listener): void
    {
        $this->listeners[trim($eventName, '\\')][] = $listener;
    }

    /**
     * Dispatches an event to all registered listeners.
     *
     * Execution Flow:
     * 1. Resolve event class name and locate matching listeners.
     * 2. Iterate through each registered listener.
     * 3. If listener is a callable, resolve arguments and invoke.
     * 4. If listener is a class string, resolve instance from DI container.
     * 5. Verify the listener implements `handle()`.
     * 6. Resolve typed payload/event arguments and invoke `handle()`.
     *
     * Logic behind the logic:
     * - By inspecting parameter types, listeners can cleanly receive either the full `DomainEventInterface`
     *   envelope, the strongly-typed `EventPayloadInterface` DTO, or a raw array without manual extraction code.
     *
     * @param object $event The event object to dispatch.
     * @throws RuntimeException If a listener class lacks a `handle` method.
     */
    public function dispatch(object $event): void
    {
        $eventClass = get_class($event);
        $normalizedClass = trim($eventClass, '\\');

        if (!isset($this->listeners[$normalizedClass])) {
            return;
        }

        foreach ($this->listeners[$normalizedClass] as $listener) {
            $this->invokeListener($listener, $event);
        }
    }

    /**
     * Clears all registered listeners and reflection caches.
     *
     * Execution Flow:
     * 1. Empty internal listeners array.
     * 2. Clear parameter type reflection cache.
     *
     * Logic behind the logic:
     * - Essential for unit testing environments to prevent state bleeding between test cases.
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->parameterTypeCache = [];
    }

    /**
     * Resolves and executes an individual listener with strongly-typed arguments.
     *
     * @param callable|string|object $listener
     * @param object $event
     */
    private function invokeListener(callable|string|object $listener, object $event): void
    {
        if (is_callable($listener)) {
            $listener($event);
            return;
        }

        $instance = is_object($listener) ? $listener : $this->container->get((string) $listener);
        assert(is_object($instance));

        if (!$instance instanceof EventListenerInterface) {
            $className = is_object($listener) ? get_class($listener) : (string) $listener;
            throw new RuntimeException("Listener [{$className}] must implement EventListenerInterface.");
        }

        $arg = $this->resolveListenerArgument($instance, $event);
        $instance->handle($arg);
    }

    /**
     * Resolves the appropriate strongly-typed argument for a listener's `handle` method.
     *
     * Execution Flow:
     * 1. Inspect cached expected parameter type for the listener class.
     * 2. If not cached, reflect on `handle()` first parameter.
     * 3. If parameter expects `EventPayloadInterface` (or subclass) and `$event` is `DomainEventInterface`,
     *    pass `$event->getPayload()`.
     * 4. If parameter expects `array` and `$event` is `DomainEventInterface`, pass `$event->getPayload()->toArray()`.
     * 5. Otherwise, pass the `$event` object directly.
     *
     * Logic behind the logic:
     * - Provides seamless backward compatibility for classic event objects while fully empowering
     *   modern DDD applications with strongly-typed payload DTOs.
     *
     * @param object $listenerInstance
     * @param object $event
     * @return mixed
     */
    private function resolveListenerArgument(object $listenerInstance, object $event): mixed
    {
        $listenerClass = get_class($listenerInstance);

        if (!array_key_exists($listenerClass, $this->parameterTypeCache)) {
            $refMethod = new ReflectionMethod($listenerInstance, 'handle');
            $params = $refMethod->getParameters();

            if (empty($params)) {
                $this->parameterTypeCache[$listenerClass] = null;
            } else {
                $type = $params[0]->getType();
                $this->parameterTypeCache[$listenerClass] = ($type instanceof ReflectionNamedType)
                    ? $type->getName()
                    : null;
            }
        }

        $expectedType = $this->parameterTypeCache[$listenerClass];

        if ($expectedType === null) {
            return $event;
        }

        // If the event is a DomainEventInterface and handler expects EventPayloadInterface
        if ($event instanceof DomainEventInterface) {
            if ($expectedType === EventPayloadInterface::class || is_subclass_of($expectedType, EventPayloadInterface::class)) {
                return $event->getPayload();
            }

            if ($expectedType === 'array') {
                return $event->getPayload()->toArray();
            }
        }

        return $event;
    }
}
