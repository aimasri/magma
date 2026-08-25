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
 * - Decoupling: Firing an event prevents the RegistrationService from needing to know about 
 *   downstream side effects (like sending welcome emails or provisioning default tenant settings).
 *
 * Teaching notes:
 * - This event is dispatched synchronously. If downstream listeners are slow, they should push 
 *   their work to the OutboxJobRepository rather than blocking the web request.
 */
class UserRegisteredEvent implements EventInterface
{
    public function __construct(
        public readonly UserRegistration $registration,
        public readonly int $userId
    ) {}
}
