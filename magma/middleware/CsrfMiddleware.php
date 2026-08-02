<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;

/**
 * Cross-Site Request Forgery (CSRF) Protection Middleware.
 * 
 * Purpose:
 * - Protect the application from malicious state-changing requests by requiring 
 *   a secret, session-bound token in every "unsafe" HTTP request (POST, PUT, etc.).
 * 
 * Why / Why this design:
 * - Implements the Synchronizer Token Pattern. This ensures that the request 
 *   originates from our application and not an attacker's iframe or script.
 * 
 * Teaching notes:
 * - It utilizes a Token Grace Period to allow for smooth navigation when users 
 *   use the browser's "Back" button or open multiple tabs.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private \Magma\security\CsrfManager $csrfManager;

    public function __construct(\Magma\security\CsrfManager $csrfManager)
    {
        $this->csrfManager = $csrfManager;
    }

    /**
     * Filters the request for CSRF compliance.
     * 
     * Execution Flow:
     * 1. Ensure a token exists for the current session (generating one if missing).
     * 2. Check the HTTP method. If it's a safe method (GET), pass the request to `$next`.
     * 3. For unsafe methods (POST/PUT/DELETE), extract the token from the payload or headers.
     * 4. Compare the submitted token against the array of valid grace-period tokens via CsrfManager.
     * 5. If invalid, instantly abort with a 403 Forbidden response.
     * 6. If valid, rotate the tokens (pruning the oldest) to prevent replay attacks.
     * 
     * Logic behind the logic:
     * - Token rotation prevents replay attacks. The grace period is a UX compromise 
     *   that prevents valid requests from failing just because the user opened a link 
     *   in a new tab (which would generate a new token and invalidate the old one).
     */
    public function process(Request $request, callable $next): Response
    {
        // Ensure a token exists in the session (generates if missing)
        $this->csrfManager->getToken();

        $method = strtoupper($request->getMethod());
        $unsafeMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];

        if (in_array($method, $unsafeMethods)) {
            // Retrieve the token from the request body or from common AJAX headers.
            // This allows both standard form submissions and JSON-based AJAX requests.
            $submittedToken = $request->request('_token') 
                ?? $request->header('X-CSRF-TOKEN') 
                ?? $request->header('X-XSRF-TOKEN');

            if (!is_string($submittedToken) || !$this->csrfManager->validateToken($submittedToken)) {
                return new Response("Forbidden: Invalid or missing CSRF token.", 403);
            }

            // Rotate the token: Add a fresh one and discard the oldest once 
            // the grace period limit is exceeded.
            // Pause rotation for AJAX/API requests to prevent rapid token exhaustion.
            if (!$request->isJsonExpected()) {
                $this->csrfManager->rotateToken();
            }
        }

        return $next($request);
    }
}