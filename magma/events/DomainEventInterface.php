<?php

declare(strict_types=1);

namespace Magma\events;

use DateTimeImmutable;
use Magma\interfaces\EventInterface;

/**
 * Title: Strongly-Typed Domain Event Interface
 *
 * Purpose:
 * - Define the enterprise contract for all Domain Events published within the Magma framework.
 * - Standardize event naming, occurrence timestamp, multi-tenant isolation, and typed payload access.
 *
 * Why / Why this design:
 * - Implements the Domain Event Pattern from Domain-Driven Design (DDD).
 * - Enforces metadata consistency (UTC timestamp, tenant scoping) across synchronous EventDispatchers, 
 *   transactional outbox tables (`OutboxJobRepository`), and asynchronous message queues.
 *
 * Teaching notes:
 * - Domain events represent immutable business facts that occurred in the past (e.g., `MenuItemCreated`, `UserRegistered`).
 * - Events should be strictly immutable once instantiated.
 */
interface DomainEventInterface extends EventInterface
{
    /**
     * Returns the unique classification or name of the domain event.
     *
     * @return string E.g. 'menu.item.created' or class name.
     */
    public function getEventName(): string;

    /**
     * Returns the immutable UTC timestamp when the domain event occurred.
     *
     * @return DateTimeImmutable
     */
    public function getOccurredAt(): DateTimeImmutable;

    /**
     * Returns the tenant/vendor ID to which this event belongs, or null for platform-level events.
     *
     * @return int|null
     */
    public function getTenantId(): ?int;

    /**
     * Returns the strongly-typed payload DTO encapsulating the event's domain attributes.
     *
     * @return EventPayloadInterface
     */
    public function getPayload(): EventPayloadInterface;
}
