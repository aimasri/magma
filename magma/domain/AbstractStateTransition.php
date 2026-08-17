<?php

declare(strict_types=1);

namespace Magma\domain;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Title: Abstract State Transition Value Object
 *
 * Purpose:
 * - Base value object encapsulating an immutable domain state transition event,
 *   including source state, target state, UTC transition timestamp, and contextual metadata.
 *
 * Why / Why this design:
 * - Value Object Immutability: Once a state transition occurs, its historical record must be immutable
 *   for audit trails, event sourcing, and ledger verification.
 * - Case-Insensitive State Normalization: Automatically trims and normalizes state strings to uppercase
 *   to eliminate subtle bugs between database enum representations and runtime inputs.
 *
 * Teaching notes:
 * - Implementing `JsonSerializable` allows state transitions to be cleanly recorded in JSONB audit columns
 *   or dispatched across queue payloads.
 */
abstract class AbstractStateTransition implements JsonSerializable
{
    protected string $fromState;
    protected string $toState;
    protected DateTimeImmutable $occurredAt;
    protected array $context;

    /**
     * @param string $fromState Originating state identifier.
     * @param string $toState Destination state identifier.
     * @param array $context Contextual metadata (e.g. user_id, reason, ip_address).
     * @param DateTimeImmutable|null $occurredAt Exact transition timestamp (defaults to UTC now).
     */
    public function __construct(
        string $fromState,
        string $toState,
        array $context = [],
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->fromState = strtoupper(trim($fromState));
        $this->toState = strtoupper(trim($toState));
        $this->context = $context;
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function hasContext(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Converts the transition into a serializable array format.
     *
     * @return array{from_state: string, to_state: string, occurred_at: string, context: array}
     */
    public function jsonSerialize(): array
    {
        return [
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'occurred_at' => $this->occurredAt->format('c'),
            'context' => $this->context,
        ];
    }
}
