<?php

namespace Magma\domain\events;

use Magma\interfaces\EventInterface;
use Magma\domain\UserRegistration;

/**
 * Title: User Registered Domain Event
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
    /**
     * Constructs a new UserRegisteredEvent.
     *
     * Logic behind the logic:
     * - By using readonly properties in PHP 8.1+, we enforce immutability for the event,
     *   ensuring that listeners cannot alter the event data mid-flight.
     *
     * @param UserRegistration $registration The domain entity representing the registered user.
     * @param array $user The raw user data from the database.
     */
    public function __construct(
        public readonly UserRegistration $registration,
        public readonly array $user
    ) {}
}
