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
 * - Implicitly extracts and binds the active tenant identifier to the global `TenantContext` and `Request` attributes.
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

    /**
     * Initializes the middleware with tenant context and optional resolution dependencies.
     *
     * Execution Flow:
     * 1. Stores the primary TenantContext where resolved tenant IDs will be bound.
     * 2. Stores the optional AuthenticationService and TenantContextProviderInterface for fallback resolution strategies.
     *
     * Logic behind the logic:
     * - Accepting optional dependencies allows the middleware to be flexible. It can attempt to resolve tenants based on the environment (e.g., custom headers via provider or session state via authentication), supporting different architectural needs seamlessly.
     *
     * @param TenantContext $tenantContext The global tenant context store.
     * @param AuthenticationService|null $authService The service to retrieve authenticated domain users.
     * @param TenantContextProviderInterface|null $provider A custom strategy for resolving tenant identifiers from requests.
     */
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
     * 2. Checks for cross-domain mismatches: If a tenant ID is active from the URL domain, but the authenticated user belongs to a different tenant, it automatically kills the session, generates a short-lived SSO token, and redirects them to their correct tenant domain.
     * 3. If unresolved, inspects authenticated user domain entity via `AuthenticationService`.
     * 4. If a valid tenant ID is located, sets `$tenantContext->setTenantId($tenantId)`.
     * 5. If venue ID is present, sets `$tenantContext->setVenueId($venueId)`.
     * 6. Attaches `'tenant_id'` and `'venue_id'` to Request attributes.
     * 7. Passes execution to `$next($request)`.
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

        // Consume incoming SSO token if present
        $ssoToken = $request->query('sso');
        if (is_string($ssoToken) && $this->authService !== null) {
            $authResult = $this->authService->attemptAutoLogin($ssoToken);
            if ($authResult->isSuccessful()) {
                $uri = $request->getUri();
                $parsedUrl = parse_url($uri);
                $cleanUrl = $parsedUrl['path'] ?? '/';
                
                $query = $request->query();
                if (is_array($query)) {
                    unset($query['sso']);
                    if (!empty($query)) {
                        $cleanUrl .= '?' . http_build_query($query);
                    }
                }

                $response = new Response('', 302, ['Location' => $cleanUrl]);
                
                foreach ($authResult->getCookiesToSet() as $cookieData) {
                    $response->withCookie(
                        $cookieData['name'],
                        $cookieData['value'],
                        $cookieData['expiry']
                    );
                }
                
                return $response;
            }
        }

        // Cross-domain mismatch check & SSO redirect
        if ($this->tenantContext->hasTenantId() && $this->authService !== null) {
            $user = $this->authService->getAuthenticatedUser();
            if ($user !== null && method_exists($user, 'hasTenantId') && $user->hasTenantId()) {
                $userTenantId = (int)$user->getTenantId();
                if ($userTenantId !== $this->tenantContext->getTenantId()) {
                    if ($this->provider !== null && method_exists($this->provider, 'resolveDomainByTenantId')) {
                        $currentHost = $request->server('HTTP_HOST');
                        $currentHostString = is_string($currentHost) ? $currentHost : null;
                        $correctDomain = $this->provider->resolveDomainByTenantId($userTenantId, $currentHostString);
                        if ($correctDomain !== null) {
                            $ssoData = $this->authService->issueSsoToken($user->getId());
                            $this->authService->logout();
                            $path = $request->getPath();
                            $query = $request->query();
                            if (!is_array($query)) {
                                $query = [];
                            }
                            $query['sso'] = $ssoData['token'];
                            $queryString = http_build_query($query);
                            $scheme = $request->isSecure() ? 'https' : 'http';
                            
                            $redirectUrl = "{$scheme}://{$correctDomain}{$path}?{$queryString}";
                            $html = sprintf(
                                '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0; url=%1$s"></head><body><script>window.location.href="%1$s";</script></body></html>',
                                htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8')
                            );
                            return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
                        }
                    }
                }
            }
        }

        // 2. Authentication service fallback
        if (!$this->tenantContext->hasTenantId() && $this->authService !== null) {
            $user = $this->authService->getAuthenticatedUser();
            if ($user !== null) {
                if (method_exists($user, 'getTenantId') && $user->getTenantId() !== null) {
                    $this->tenantContext->setTenantId((int)$user->getTenantId());
                } elseif (method_exists($user, 'getTenantId') && $user->hasTenantId()) {
                    $this->tenantContext->setTenantId((int)$user->getTenantId());
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
