<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\Session;

/**
 * Title: Session Timeout Middleware
 *
 * Purpose:
 * - Enforce inactivity timeouts for authenticated users.
 * - Allow dynamic expiration times based on user roles (e.g., Admins get longer sessions).
 *
 * Why / Why this design:
 * - This adheres to the Single Responsibility Principle. The low-level Session Handler 
 *   manages raw persistence up to an absolute maximum TTL, while this Middleware handles 
 *   complex business rules regarding user inactivity.
 *
 * Teaching notes:
 * - Inactivity timeouts are critical for security compliance (like PCI-DSS or HIPAA), 
 *   ensuring that if a user walks away from their terminal, an attacker cannot hijack 
 *   their active session indefinitely.
 */
class SessionTimeoutMiddleware implements MiddlewareInterface
{
    private Session $session;
    private int $standardTimeout = 1800; // 30 minutes
    private int $adminTimeout = 7200; // 2 hours

    /**
     * Initializes the middleware with the required session handler.
     *
     * Execution Flow:
     * 1. Stores the provided Session instance for state management.
     *
     * Logic behind the logic:
     * - Passing the Session instance via constructor injection complies with the Dependency Inversion Principle, decoupling the middleware from global session state or static helpers.
     *
     * @param Session $session The HTTP session instance.
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Processes the request and enforces session timeouts.
     *
     * Execution Flow:
     * 1. Check if the user is authenticated. If not, pass the request down the chain.
     * 2. Retrieve the last activity timestamp.
     * 3. Determine the allowed timeout duration based on the user's role.
     * 4. If the inactivity exceeds the allowed duration, destroy the session and redirect.
     * 5. Otherwise, update the `last_activity` timestamp to `time()` and continue.
     *
     * Logic behind the logic:
     * - We track the timestamp of the last request server-side (`last_activity`) rather than 
     *   relying on cookie expiration. Cookies can be hijacked; server-side timestamp 
     *   validation guarantees inactive sessions are irrevocably destroyed.
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $this->session->get('user');

        if (is_array($user)) {
            $lastActivity = $this->session->get('last_activity');
            $lastActivity = is_numeric($lastActivity) ? (int)$lastActivity : null;
            $currentTime = time();
            
            $allowedTimeout = $this->standardTimeout;
            
            $role = isset($user['role']) && is_scalar($user['role']) ? (string)$user['role'] : null;
            if (\Magma\enums\UserRole::isTenantRole($role)) {
                $allowedTimeout = $this->adminTimeout;
            }

            if ($lastActivity && ($currentTime - $lastActivity > $allowedTimeout)) {
                // Timeout exceeded. Destroy session and redirect to login.
                $this->session->destroy();
                
                return (new \Magma\http\RedirectResponse('/login'))
                    ->withCookie('remember_user', '', time() - 3600);
            }

            // Update last activity timestamp
            $this->session->set('last_activity', $currentTime);
        }

        return $next($request);
    }
}
