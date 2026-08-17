<?php

namespace Magma\interfaces;

/**
 * Title: Event Dispatcher Contract
 *
 * Purpose:
 * - Defines the standard methods for registering listeners and dispatching events.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle. Services that trigger events should depend on
 *   this interface, not the concrete dispatcher, allowing for easy mocking during tests.
 *
 * Teaching notes:
 * - Notice that $listener accepts both callables and strings. Accepting strings
 *   allows us to defer the instantiation of the listener class until the event
 *   is actually fired, saving memory.
 */
interface EventDispatcherInterface
{
    /**
     * Registers a listener for a specific event.
     * 
     * @param string $eventName The fully qualified class name of the event.
     * @param callable|string $listener The listener to register (callable or class name).
     */
    public function listen(string $eventName, callable|string $listener): void;

    /**
     * Dispatches an event to all registered listeners.
     * 
     * @param object $event The event object to dispatch.
     */
    public function dispatch(object $event): void;

    /**
     * Clears all registered listeners.
     */
    public function clear(): void;
}
