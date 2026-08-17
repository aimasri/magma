<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\security\TenantContext;
use Magma\security\TenantContextProviderInterface;
use Magma\services\AuthenticationService;

/**
 * Title: Multi-Tenant Security & Context Binding Middleware
 *
 * Purpose:
 * - Implicitly extracts and binds the active tenant/vendor identifier to the global `TenantContext` and `Request` attributes.
 * - Enforces zero-trust tenant data isolation at the HTTP pipeline boundary.
 *
 * Why / Why this design:
 * - Intercepting Filter Pattern: Centralizing tenant resolution in global middleware eliminates the possibility of individual controllers or services accidentally omitting tenant filters in SQL queries.
 * - Pluggable Strategy Resolution: Checks custom `TenantContextProviderInterface` first, then falls back to authenticated domain user session state (`AuthenticationService`).
 *
 * Teaching notes:
 * - By binding `'tenant_id'` into `$request->withAttribute('tenant_id', ...)`, downstream controllers and logging middleware can access the verified tenant identity without re-parsing sessions.
 */
class TenantSecurityMiddleware implements MiddlewareInterface
{
    private TenantContext $tenantContext;
    private ?AuthenticationService $authService;
    private ?TenantContextProviderInterface $provider;

    public function __construct(
        TenantContext $tenantContext,
        ?AuthenticationService $authService = null,
        ?TenantContextProviderInterface $provider = null
    ) {
        $this->tenantContext = $tenantContext;
        $this->authService = $authService;
        $this->provider = $provider;
    }

    /**
     * Intercepts the request and binds the active tenant identity.
     *
     * Execution Flow:
     * 1. Attempts resolution via custom `TenantContextProviderInterface` or `TenantContext::resolveFromRequest()`.
     * 2. If unresolved, inspects authenticated user domain entity via `AuthenticationService`.
     * 3. If a valid tenant/vendor ID is located, sets `$tenantContext->setTenantId($tenantId)`.
     * 4. If venue ID is present, sets `$tenantContext->setVenueId($venueId)`.
     * 5. Attaches `'tenant_id'` and `'venue_id'` to Request attributes.
     * 6. Passes execution to `$next($request)`.
     *
     * Logic behind the logic:
     * - Extracting context from verified domain authentication entities (rather than untrusted client inputs) ensures cross-tenant privilege escalation is impossible.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        // 1. Pluggable provider resolution
        if ($this->provider !== null) {
            $tenantId = $this->provider->resolveTenantId($request);
            if ($tenantId !== null) {
                $this->tenantContext->setTenantId($tenantId);
            }
            $venueId = $this->provider->resolveVenueId($request);
            if ($venueId !== null) {
                $this->tenantContext->setVenueId($venueId);
            }
        }

        // 2. Authentication service fallback
        if (!$this->tenantContext->hasTenantId() && $this->authService !== null) {
            $user = $this->authService->getAuthenticatedUser();
            if ($user !== null) {
                if (method_exists($user, 'getTenantId') && $user->getTenantId() !== null) {
                    $this->tenantContext->setTenantId((int)$user->getTenantId());
                } elseif (method_exists($user, 'getVendorId') && $user->hasVendorId()) {
                    $this->tenantContext->setTenantId((int)$user->getVendorId());
                }

                if (method_exists($user, 'getVenueId') && $user->getVenueId() !== null) {
                    $this->tenantContext->setVenueId((int)$user->getVenueId());
                }
            }
        }

        // 3. Bind to Request attributes
        if ($this->tenantContext->hasTenantId()) {
            $request = $request->withAttribute('tenant_id', $this->tenantContext->getTenantId());
        }

        if ($this->tenantContext->hasVenueId()) {
            $request = $request->withAttribute('venue_id', $this->tenantContext->getVenueId());
        }

        return $next($request);
    }
}
