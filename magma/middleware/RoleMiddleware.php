<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;

/**
 * RoleMiddleware — generic role-based authorization guard.
 * 
 * Purpose:
 * - Validates that the authenticated user possesses one of the required roles.
 * - Redirects unauthorized users to a specified fallback route (e.g., '/user' or '/admin').
 * 
 * Why / Why this design:
 * - Implements the Authorization Guard pattern as a middleware. By abstracting role 
 *   checks into the HTTP pipeline, we ensure that business logic controllers are completely 
 *   decoupled from access control mechanisms.
 * 
 * Teaching notes:
 * - This class demonstrates declarative authorization by allowing the allowed roles 
 *   to be injected via the constructor directly in the routing configuration.
 * - Centralizing role checks prevents controllers from duplicating authorization logic,
 *   strictly adhering to the Single Responsibility Principle.
 */
class RoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;
    private string $redirectPath;

    public function __construct(array|string $allowedRoles, string $redirectPath = '/')
    {
        $this->allowedRoles = (array) $allowedRoles;
        $this->redirectPath = $redirectPath;
    }

    /**
     * Executes the middleware layer.
     * 
     * Execution Flow:
     * 1. Retrieve the authenticated user from the session.
     * 2. Verify that a user exists and has a defined 'role'.
     * 3. Check if the user's role is within the acceptable whitelist ($allowedRoles).
     * 4. If any check fails, immediately return a RedirectResponse to the fallback path.
     * 5. If authorized, pass the request to the next layer in the pipeline.
     * 
     * Logic behind the logic:
     * - We use `in_array(..., true)` for strict type checking to prevent any unexpected 
     *   type coercion bypasses during role validation.
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $request->session('user');
        
        if (!$user || !isset($user['role']) || !in_array($user['role'], $this->allowedRoles, true)) {
            return new RedirectResponse($this->redirectPath);
        }
        
        return $next($request);
    }
}
