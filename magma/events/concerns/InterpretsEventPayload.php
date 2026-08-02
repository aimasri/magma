<?php

namespace Magma\events\concerns;

/**
 * Title: Interprets Event Payload Trait
 *
 * Purpose:
 * - A utility trait for event listeners to automatically extract and normalize 
 *   payloads from polymorphic event inputs.
 * - Handles both synchronous domain event objects and asynchronous array payloads.
 *
 * Why / Why this design:
 * - Adheres to Interface Segregation & SOLID by allowing event listeners to 
 *   handle synchronous event objects or asynchronous raw array payloads seamlessly.
 * - Promotes DRY (Don't Repeat Yourself) by centralizing payload extraction logic.
 *
 * Teaching notes:
 * - Traits allow horizontal code reuse across disparate class hierarchies.
 * - This is particularly useful in systems where an event might be handled instantly in memory or serialized for a delayed queue worker.
 */
trait InterpretsEventPayload
{
    /**
     * Normalizes the incoming event payload into a standard array format.
     *
     * 1. Checks if the incoming event is already an array, returning it directly if so.
     * 2. Checks if the event object exposes a `getPayload` method and invokes it.
     * 3. Falls back to extracting all public properties of the object using `get_object_vars`.
     *
     * Logic behind the logic:
     * - The fallback mechanism ensures resilience and broad compatibility with simple DTO-style events 
     *   that may not strictly implement a formal Event interface.
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
