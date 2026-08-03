<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;

/**
 * AuthMiddleware — route access protection.
 * 
 * Purpose:
 * - Ensures the user is authenticated before allowing access to the route.
 * - Redirects guests to the login page.
 * 
 * Why / Why this design:
 * - Utilizes the Onion Architecture (Middleware pipeline). It intercepts the 
 *   request early, preventing protected controllers from ever booting if the 
 *   session is invalid.
 * 
 * Teaching notes:
 * - Using middleware for authentication keeps controllers thin and prevents 
 *   duplication of security checks across multiple routes.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private \Magma\http\Session $session;

    public function __construct(\Magma\http\Session $session)
    {
        $this->session = $session;
    }

    /**
     * Executes the middleware layer to verify authentication.
     * 
     * Execution Flow:
     * 1. Inspects the session for a valid 'user' payload.
     * 2. If the user is missing, it immediately halts the request and issues a RedirectResponse to the login page.
     * 3. If the user is present, it passes the request down the pipeline to the next layer.
     * 
     * Logic behind the logic:
     * Validating authentication at the middleware layer prevents unauthorized users from executing any controller code, thereby ensuring zero unintended side effects from protected routes.
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        if (!$this->session->get('user')) {
            return new RedirectResponse('/login');
        }
        return $next($request);
    }
}
