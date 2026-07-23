<?php

namespace Magma\events\concerns;

/**
 * Trait InterpretsEventPayload
 *
 * Purpose:
 * - A utility trait for event listeners to automatically extract and normalize 
 *   payloads from polymorphic event inputs.
 *
 * Why / Why this design:
 * - Adheres to Interface Segregation & SOLID by allowing event listeners to 
 *   handle synchronous event objects or asynchronous raw array payloads seamlessly.
 */
trait InterpretsEventPayload
{
    /**
     * Extracts the payload array from an event object or raw array.
     *
     * @param object|array $event The incoming event (either an object from the synchronous 
     *                            dispatcher, or a raw array from a queue worker).
     * @return array The normalized payload data.
     */
    protected function extractPayload(object|array $event): array
    {
        if (is_array($event)) {
            return $event;
        }

        // If the event object has a getPayload method, use it
        if (method_exists($event, 'getPayload')) {
            return $event->getPayload();
        }

        // Otherwise, extract public properties as a fallback
        return get_object_vars($event);
    }
}
