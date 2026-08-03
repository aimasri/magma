<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\Session;

/**
 * Session Timeout Middleware
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

        if ($user) {
            $lastActivity = $this->session->get('last_activity');
            $currentTime = time();
            
            $allowedTimeout = $this->standardTimeout;
            if (\Magma\enums\UserRole::isVendorRole($user['role'] ?? null)) {
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
