<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\security\TenantContext;
use Magma\services\AuthenticationService;

/**
 * Title: Tenant Context Middleware (Legacy Compatibility Facade)
 * 
 * Purpose:
 * - Binds the active tenant/vendor identifier to the global `TenantContext` and `Request` attributes.
 * - Serves as a compatibility layer matching `TenantSecurityMiddleware`.
 * 
 * Why / Why this design:
 * - Maintains backward compatibility for downstream applications already referencing `TenantContextMiddleware` in their bootstrap or index files.
 *
 * Teaching notes:
 * - Multi-tenant scoping at the middleware layer prevents cross-tenant data leakage by ensuring all subsequent database queries are scoped.
 */
class TenantContextMiddleware implements MiddlewareInterface
{
    private TenantContext $tenantContext;
    private AuthenticationService $authService;

    public function __construct(TenantContext $tenantContext, AuthenticationService $authService)
    {
        $this->tenantContext = $tenantContext;
        $this->authService = $authService;
    }

    public function process(RequestInterface $request, callable $next): Response
    {
        $user = $this->authService->getAuthenticatedUser();

        if ($user !== null) {
            if (method_exists($user, 'hasVendorId') && $user->hasVendorId()) {
                $vendorId = (int)$user->getVendorId();
                $this->tenantContext->setVendorId($vendorId);
                $request = $request->withAttribute('tenant_id', $vendorId);
            } elseif (method_exists($user, 'getTenantId') && $user->getTenantId() !== null) {
                $tenantId = (int)$user->getTenantId();
                $this->tenantContext->setTenantId($tenantId);
                $request = $request->withAttribute('tenant_id', $tenantId);
            }
        }

        return $next($request);
    }
}
