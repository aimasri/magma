<?php

namespace Magma\providers;

use Magma\container\ServiceProviderInterface;
use Magma\container\Container;
use Magma\interfaces\EventDispatcherInterface;

/**
 * Title: Event Service Provider
 *
 * Purpose:
 * - Bootstraps the application's Event-Driven Architecture by registering
 *   all listeners to their respective events.
 *
 * Why / Why this design:
 * - Encapsulates event registration in one dedicated provider. This prevents the
 *   CoreServiceProvider from becoming a messy "God Provider" as the app grows.
 *
 * Teaching notes:
 * - The $listen array maps an Event Class to an array of Listener Classes.
 *   These listeners remain strings at boot time. They are only resolved by
 *   the Container when the event is actually dispatched.
 */
class EventServiceProvider implements ServiceProviderInterface
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<string>>
     */
    protected array $listen = [
        \Magma\domain\events\UserRegisteredEvent::class => [
            \Magma\listeners\SendWelcomeEmailListener::class,
        ],
    ];

    /**
     * Registers the event listeners into the application's event dispatcher.
     * 
     * Execution Flow:
     * 1. Resolves the EventDispatcherInterface from the dependency injection container.
     * 2. Iterates over the predefined `$listen` array mapping events to listeners.
     * 3. Registers each listener string to its corresponding event on the dispatcher.
     * 
     * Logic behind the logic:
     * - Centralizing listener registration here keeps bootstrap code clean and separates event configuration from dispatcher implementation.
     * 
     * @param Container $container The dependency injection container.
     */
    public function register(Container $container): void
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
    }
}
