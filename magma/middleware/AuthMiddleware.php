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
    public function process(Request $request, callable $next): Response
    {
        if (!$request->session('user')) {
            return new RedirectResponse('/login');
        }
        return $next($request);
    }
}
