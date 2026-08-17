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

    public function __construct(string $fromState, string $toState, string $message = "")
    {
        $this->fromState = $fromState;
        $this->toState = $toState;

        if ($message === "") {
            $message = "Cannot transition from state [{$fromState}] to [{$toState}].";
        }

        parent::__construct($message);
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }
}
