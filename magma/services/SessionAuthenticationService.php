<?php

namespace Magma\services;

use Magma\http\Session;
use Magma\domain\AuthUser;
use Magma\contracts\ClockInterface;

/**
 * Title: Session Authentication Service
 *
 * Purpose:
 * - Manage the active PHP session state for authenticated users.
 *
 * Why / Why this design:
 * - Extracted from the monolithic AuthenticationService to strictly enforce SRP.
 * - This service handles only session initialization and destruction, keeping 
 *   credential logic and persistent token logic fully decoupled.
 *
 * Teaching notes:
 * - Always rotate the session ID upon successful login to prevent Session Fixation attacks. This service is solely responsible for that lifecycle.
 */
class SessionAuthenticationService
{
    protected Session $session;
    protected ClockInterface $clock;

    /**
     * Constructs the SessionAuthenticationService.
     *
     * Execution Flow:
     * 1. Injects the HTTP Session and Clock dependencies.
     * 2. Assigns them to protected properties to manage session state and timestamps.
     *
     * Logic behind the logic:
     * - Mockable Time: Injecting ClockInterface allows time manipulation in tests,
     *   preventing flaky assertions regarding session login times.
     *
     * @param Session $session
     * @param ClockInterface $clock
     */
    public function __construct(Session $session, ClockInterface $clock)
    {
        $this->session = $session;
        $this->clock = $clock;
    }

    /**
     * Initializes the user session upon successful authentication.
     * 
     * Execution Flow:
     * 1. Regenerate the session ID immediately upon login to prevent Session Fixation attacks.
     * 2. Store non-sensitive user metadata into the session array via the entity.
     */
    public function login(AuthUser $authUser): void
    {
        $this->session->regenerate(true);
        $this->session->set('user', $authUser->toSessionArray());
        $this->session->set('login_time', $this->clock->now()->getTimestamp());
    }

    /**
     * Destroys the active session.
     */
    public function logout(): void
    {
        $this->session->destroy();
    }

    /**
     * Retrieves the currently authenticated user from the session.
     * 
     * @return AuthUser|null
     */
    public function getAuthenticatedUser(): ?AuthUser
    {
        $userData = $this->session->get('user');
        if (is_array($userData)) {
            $cleanData = [];
            foreach ($userData as $k => $v) {
                if (is_string($k)) {
                    $cleanData[$k] = $v;
                }
            }
            return new AuthUser($cleanData);
        }
        return null;
    }
}
