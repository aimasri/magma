<?php

namespace Magma\interfaces;

/**
 * Event Dispatcher Contract
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
    public function listen(string $eventName, callable|string $listener): void;
    public function dispatch(object $event): void;
}
