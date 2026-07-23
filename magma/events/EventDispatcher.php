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

    public function listen(string $eventName, callable|string $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

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

    public function clear(): void
    {
        $this->listeners = [];
    }
}
