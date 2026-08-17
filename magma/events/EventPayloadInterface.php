<?php

declare(strict_types=1);

namespace Magma\events;

/**
 * Title: Strongly-Typed Event Payload Interface
 *
 * Purpose:
 * - Define the contract for domain event payloads.
 * - Enforce deterministic serialization of domain event parameters into structured arrays for outbox and queue workers.
 *
 * Why / Why this design:
 * - Domain-Driven Design (DDD) & CQRS: Eliminates untyped associative array primitives across domain boundaries.
 * - Guarantees that payloads can be serialized to JSON and deserialized across background worker daemons.
 *
 * Teaching notes:
 * - Every Domain Event must bundle its payload within a class implementing this interface.
 */
interface EventPayloadInterface
{
    /**
     * Converts the strongly-typed payload into an associative array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
