<?php

declare(strict_types=1);

namespace Magma\domain\events;

use DateTimeImmutable;
use Magma\events\DomainEventInterface;
use Magma\events\EventPayloadInterface;

/**
 * Title: Password Reset Requested Event
 *
 * Purpose:
 * - Represents the domain intent that a user has requested a password reset.
 * - Carries immutable data (email, name, token) required to trigger side effects.
 *
 * Why this design:
 * - Event-Driven Architecture. It decouples the core domain logic (validating the user and generating a token) from side-effect execution (such as dispatching an email via a Queue worker), adhering to SRP.
 *
 * Teaching notes:
 * - Domain events must always reflect things that happened in the past, hence the name `Requested` instead of `Request`.
 */
class PasswordResetRequestedEvent implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    /**
     * Initializes a new Password Reset Requested Event.
     *
     * Logic behind the logic:
     * - Captures the exact moment of the event via DateTimeImmutable for accurate auditing and temporal processing.
     * - Utilizes constructor property promotion for strict, immutable assignment.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $token
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    /**
     * Retrieves the strictly-typed event name for pub/sub routing.
     *
     * Logic behind the logic:
     * - Hardcoding the event name prevents magic string typos and ensures reliable routing in the EventDispatcher.
     */
    public function getEventName(): string
    {
        return 'password.reset.requested';
    }

    /**
     * Retrieves the exact timestamp when this event was instantiated.
     *
     * Logic behind the logic:
     * - Returning a DateTimeImmutable prevents downstream handlers from accidentally mutating the event's historical timestamp.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Retrieves the associated tenant ID for this event.
     *
     * Logic behind the logic:
     * - Returns null as password resets are typically handled at the system/identity level, spanning across potential tenant boundaries.
     */
    public function getTenantId(): ?int
    {
        return null;
    }

    /**
     * Constructs and retrieves the serialized payload for this domain event.
     *
     * Execution Flow:
     * 1. Instantiates a self-contained anonymous class implementing EventPayloadInterface.
     * 2. Binds the immutable event properties (email, name, token) into the payload.
     * 3. Provides a toArray mapping for queue serialization.
     *
     * Logic behind the logic:
     * - Encapsulates payload logic dynamically without needing a separate concrete DTO class, keeping the event package cohesive.
     */
    public function getPayload(): EventPayloadInterface
    {
        return new class($this->email, $this->name, $this->token) implements EventPayloadInterface {
            /**
             * Initializes the dynamic event payload.
             *
             * Logic behind the logic:
             * - Employs constructor property promotion to mirror the parent event's properties efficiently.
             */
            public function __construct(
                private string $email,
                private string $name,
                private string $token
            ) {}

            /**
             * Serializes the payload into a primitive array structure.
             *
             * Logic behind the logic:
             * - Required by EventPayloadInterface for seamless encoding during outbox or message broker publishing.
             */
            public function toArray(): array
            {
                return [
                    'email' => $this->email,
                    'name' => $this->name,
                    'token' => $this->token,
                ];
            }
        };
    }
}
