<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\security\TenantContext;

/**
 * Title: Tenant Context Middleware
 * 
 * Purpose:
 * - Implicitly binds the tenantId to the TenantContext (and Request attributes) 
 *   to completely remove the developer's ability to accidentally hardcode it.
 * - Intercepts the request lifecycle to verify and set tenant isolation boundaries.
 * 
 * Why this design:
 * - If a developer forgets to request $tenantId properly, data leaks across tenants. 
 *   By using a global middleware to enforce this context, we guarantee data isolation 
 *   by default.
 * - Implements the Intercepting Filter pattern.
 *
 * Teaching notes:
 * - Multi-tenancy is challenging; setting the context globally early in the middleware stack is a proven strategy to prevent Cross-Tenant Data Leaks.
 * - Compare to Laravel's global scopes which could automatically apply this tenant ID to all database queries.
 */
class TenantContextMiddleware implements MiddlewareInterface
{
    private TenantContext $tenantContext;
    private \Magma\services\AuthenticationService $authService;

    public function __construct(TenantContext $tenantContext, \Magma\services\AuthenticationService $authService)
    {
        $this->tenantContext = $tenantContext;
        $this->authService = $authService;
    }

    /**
     * Processes the incoming request to inject tenant context.
     *
     * Execution Flow:
     * 1. Retrieve the authenticated user data from the session.
     * 2. If present, hydrate a domain AuthUser object.
     * 3. Check if the user is associated with a vendor (tenant).
     * 4. If so, bind the vendor ID to the global TenantContext and request attributes.
     * 5. Delegate the request to the next middleware in the chain.
     *
     * Logic behind the logic:
     * - Fail-safe isolation: Context is populated dynamically from verified session data, avoiding reliance on easily manipulated client inputs like headers or query parameters for tenant identification.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $this->authService->getAuthenticatedUser();
        
        if ($user) {
            
            if ($user->hasVendorId()) {
                $vendorId = $user->getVendorId();
                $this->tenantContext->setVendorId($vendorId);
                $request = $request->withAttribute('tenant_id', $vendorId);
            }
        }
        
        return $next($request);
    }
}
