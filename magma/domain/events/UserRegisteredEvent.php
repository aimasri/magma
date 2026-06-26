<?php

namespace Magma\domain\events;

use Magma\interfaces\EventInterface;
use Magma\domain\UserRegistration;

/**
 * User Registered Domain Event
 *
 * Purpose:
 * - Represents the business event that a new user has successfully registered.
 *
 * Why / Why this design:
 * - Domain Event Pattern: Encapsulates the state changes that occurred in the domain
 *   so that decoupled listeners can react to them.
 *
 * Teaching notes:
 * - By strictly typing the payload to accept the rich Domain Entity (`UserRegistration`),
 *   we guarantee that listeners receive valid, fully-formed data without needing to
 *   re-query the database or re-validate arrays.
 */
class UserRegisteredEvent implements EventInterface
{
    public function __construct(
        public readonly UserRegistration $registration,
        public readonly array $user
    ) {}
}
