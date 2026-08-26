<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\config\Config;
use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Security Headers Middleware
 *
 * Purpose:
 * - Injects defensive HTTP security response headers into all outgoing HTTP responses.
 * - Protects against Clickjacking (X-Frame-Options), MIME sniffing (X-Content-Type-Options), Cross-Site Scripting (CSP), and unencrypted transmissions (HSTS).
 * - Provides a configurable Content Security Policy (CSP) supporting external font/asset providers (Google Fonts, CDNs) without degrading security invariants.
 *
 * Why / Why this design:
 * - Centralizing security headers in the outermost middleware layer guarantees that every route and view is protected by default.
 * - Configurable CSP: Allows downstream applications to register external origins (e.g. `https://fonts.googleapis.com`, `https://fonts.gstatic.com`) via `.env` or application config without modifying core framework code.
 *
 * Teaching notes:
 * - Content-Security-Policy (CSP) is the single most effective defense against modern stored and reflected Cross-Site Scripting (XSS).
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $cspDirectives;

    /**
     * @param array<int, string>|null $customDirectives
     */
    public function __construct(?array $customDirectives = null)
    {
        $defaultCsp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $configDirectives = Config::get('CSP_DIRECTIVES');
        if (is_string($configDirectives)) {
            $configDirectives = array_map('trim', explode(';', $configDirectives));
        }

        $this->cspDirectives = $customDirectives ?? (is_array($configDirectives) ? $configDirectives : $defaultCsp);
    }

    /**
     * Injects hardened security headers into the outgoing HTTP response.
     *
     * Execution Flow:
     * 1. Passes the request to `$next` to obtain the inner response.
     * 2. Adds `X-Frame-Options: DENY` (Clickjacking defense).
     * 3. Adds `X-Content-Type-Options: nosniff` (MIME sniffing defense).
     * 4. Adds `Strict-Transport-Security` (HTTPS enforcement).
     * 5. Compiles and sets the `Content-Security-Policy` header.
     * 6. Sets `Referrer-Policy: strict-origin-when-cross-origin`.
     * 7. Returns the decorated Response.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $cspHeader = implode('; ', array_filter($this->cspDirectives));

        $response->addHeader('X-Frame-Options', 'DENY');
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        $response->addHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->addHeader('Content-Security-Policy', $cspHeader);
        $response->addHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->addHeader('X-XSS-Protection', '0'); // Modern best practice: disable buggy browser XSS auditors

        return $response;
    }
}
