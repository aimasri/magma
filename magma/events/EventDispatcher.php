<?php

namespace Magma\events;

use Magma\interfaces\EventDispatcherInterface;
use Magma\container\Container;

/**
 * Core Event Dispatcher
 *
 * Purpose:
 * - Manages the registry of event listeners and routes fired events to them.
 *
 * Why / Why this design:
 * - Implements the Mediator / Pub-Sub pattern. It decouples the "Subject" (the service
 *   triggering the event) from the "Observers" (the listeners executing side effects).
 *
 * Teaching notes:
 * - We inject the Dependency Injection Container here so we can resolve string-based
 *   listener class names "just in time". If we instantiated all listeners at boot time,
 *   a large app would consume massive memory for listeners that never get executed.
 */
class EventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Registers a listener for a specific event.
     * 
     * Execution Flow:
     * 1. Appends the given listener (callable or class name) to the array of listeners for the specified event name.
     * 
     * Logic behind the logic:
     * - Storing class names as strings rather than instantiated objects allows for lazy instantiation, saving memory and processing time during application boot.
     * 
     * @param string $eventName The fully qualified class name of the event.
     * @param callable|string $listener The listener to register.
     */
    public function listen(string $eventName, callable|string $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Dispatches an event to all registered listeners.
     * 
     * Execution Flow:
     * 1. Determine the event name by getting the class name of the passed event object.
     * 2. Check if there are any listeners registered for this event. If not, return early.
     * 3. Iterate over each registered listener.
     * 4. If the listener is a callable, execute it directly with the event as an argument.
     * 5. If the listener is a string (class name), resolve it from the DI container.
     * 6. Verify the resolved instance has a 'handle' method and invoke it, throwing an exception otherwise.
     * 
     * Logic behind the logic:
     * - Using the container to resolve string-based listeners guarantees that any dependencies the listener requires are automatically injected just prior to execution.
     * 
     * @param object $event The event object to dispatch.
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if (is_callable($listener)) {
                $listener($event);
            } elseif (is_string($listener)) {
                $instance = $this->container->get($listener);
                if (method_exists($instance, 'handle')) {
                    $instance->handle($event);
                } else {
                    throw new \RuntimeException("Listener [{$listener}] must implement a 'handle' method.");
                }
            }
        }
    }

    /**
     * Clears all registered listeners.
     * 
     * Execution Flow:
     * 1. Resets the internal listeners array to an empty array.
     * 
     * Logic behind the logic:
     * - Provides a straightforward way to tear down state, which is especially useful during unit testing to prevent state bleed between tests.
     */
    public function clear(): void
    {
        $this->listeners = [];
    }
}
