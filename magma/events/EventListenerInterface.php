<?php

declare(strict_types=1);

namespace Magma\events;

/**
 * Title: Event Listener Interface
 * Purpose: Defines the strict contract for all domain event listeners.
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
