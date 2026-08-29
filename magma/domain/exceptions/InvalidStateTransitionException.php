<?php

declare(strict_types=1);

namespace Magma\domain\exceptions;

use RuntimeException;

/**
 * Title: Invalid State Transition Exception
 *
 * Purpose:
 * - Thrown when a domain entity attempts an unauthorized, unmapped, or terminal state machine transition.
 *
 * Why / Why this design:
 * - Domain Exception Isolation: Prevents illegal state transitions from being processed by repositories or services.
 *
 * Teaching notes:
 * - Explicit domain exceptions allow controllers to render 422 Unprocessable Entity responses with helpful error details.
 */
class InvalidStateTransitionException extends RuntimeException
{
    private string $fromState;
    private string $toState;

    /**
     * Constructs the Invalid State Transition Exception.
     *
     * Execution Flow:
     * 1. Captures the attempted origin state and destination state.
     * 2. Generates a standard exception message if a custom one is omitted.
     * 3. Calls the parent RuntimeException constructor.
     *
     * Logic behind the logic:
     * - Enforces explicitly mapping the failed transition path, aiding debugging and logging.
     */
    public function __construct(string $fromState, string $toState, string $message = "")
    {
        $this->fromState = $fromState;
        $this->toState = $toState;

        if ($message === "") {
            $message = "Cannot transition from state [{$fromState}] to [{$toState}].";
        }

        parent::__construct($message);
    }

    /**
     * Retrieves the origin state of the failed transition.
     *
     * Logic behind the logic:
     * - Provides programmatic access to the starting state for advanced error handling or metrics gathering.
     */
    public function getFromState(): string
    {
        return $this->fromState;
    }

    /**
     * Retrieves the destination state of the failed transition.
     *
     * Logic behind the logic:
     * - Enables presentation layers or domain observers to inspect which illegal state was requested.
     */
    public function getToState(): string
    {
        return $this->toState;
    }
}
