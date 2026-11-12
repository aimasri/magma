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
 * - Enforces RFC 6797 §7.2 compliance by emitting Strict-Transport-Security solely over secure HTTPS connections.
 * - Provides a configurable Content Security Policy (CSP) supporting external font/asset providers (Google Fonts, CDNs) without degrading security invariants.
 *
 * Why / Why this design:
 * - Centralizing security headers in the outermost middleware layer guarantees that every route and view is protected by default.
 * - RFC 6797 Compliance: Emitting HSTS headers over plain HTTP violates RFC 6797 §7.2 and risks locking developers out of local plain-HTTP environments (e.g. `http://*.test`). Conditional emission protects development while hardening production TLS.
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
            "script-src 'self'",
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

        $this->cspDirectives = $customDirectives ?? (is_array($configDirectives) ? array_map(fn($v) => is_scalar($v) ? (string)$v : '', array_values($configDirectives)) : $defaultCsp);
    }

    /**
     * Injects hardened security headers into the outgoing HTTP response.
     *
     * Execution Flow:
     * 1. Passes the request to `$next` to obtain the inner response.
     * 2. Adds `X-Frame-Options: DENY` (Clickjacking defense).
     * 3. Adds `X-Content-Type-Options: nosniff` (MIME sniffing defense).
     * 4. Conditionally adds `Strict-Transport-Security` only if `$request->isSecure()` is true (RFC 6797 §7.2).
     * 5. Compiles and sets the `Content-Security-Policy` header.
     * 6. Sets `Permissions-Policy` and `Referrer-Policy: strict-origin-when-cross-origin`.
     * 7. Returns the decorated Response.
     *
     * Logic behind the logic:
     * - RFC 6797 §7.2 explicitly dictates that user agents MUST ignore HSTS headers received over non-secure transport, and servers SHOULD NOT emit them. Guarding HSTS behind `isSecure()` prevents browser cache corruption during local HTTP development while enforcing rigorous transport encryption on HTTPS.
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
        if ($request->isSecure()) {
            $response->addHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $response->addHeader('Content-Security-Policy', $cspHeader);
        $response->addHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->addHeader('X-XSS-Protection', '0'); // Modern best practice: disable buggy browser XSS auditors

        return $response;
    }
}
