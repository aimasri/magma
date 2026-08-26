<?php

declare(strict_types=1);

namespace Magma\domain\events;

use DateTimeImmutable;
use Magma\events\DomainEventInterface;
use Magma\events\EventPayloadInterface;

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
