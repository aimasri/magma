<?php

declare(strict_types=1);

namespace Magma\events;

/**
 * Title: Event Listener Interface
 *
 * Purpose:
 * - Defines the strict contract for all domain event listeners.
 *
 * Why this design:
 * - Implements the Observer pattern. Forcing a common interface allows the EventDispatcher to type-hint generically and execute listeners polymorphically without knowing their concrete implementations.
 *
 * Teaching notes:
 * - Listeners should generally perform isolated, side-effect operations (e.g., logging, queuing emails, syncing to third-party APIs).
 */
interface EventListenerInterface
{
    /**
     * Handle the dispatched event.
     *
     * @param mixed $event The event object being dispatched.
     * @return void
     */
    public function handle(mixed $event): void;
}
