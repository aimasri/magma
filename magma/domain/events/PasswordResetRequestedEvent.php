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

    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $token
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getEventName(): string
    {
        return 'password.reset.requested';
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getTenantId(): ?int
    {
        return null;
    }

    public function getPayload(): EventPayloadInterface
    {
        return new class($this->email, $this->name, $this->token) implements EventPayloadInterface {
            public function __construct(
                private string $email,
                private string $name,
                private string $token
            ) {}

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
