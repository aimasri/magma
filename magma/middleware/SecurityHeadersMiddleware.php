<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Security Headers Middleware
 *
 * Purpose:
 * - Injects strict HTTP security headers into every outgoing response.
 *
 * Why / Why this design:
 * - Centralizing this in middleware guarantees that no route can bypass security controls.
 * - Adheres to OWASP best practices for protecting against Clickjacking, MIME-sniffing, and XSS.
 *
 * Teaching notes:
 * - This acts on the outward flow of the middleware onion. The request goes in, hits the controller,
 *   a Response object is generated, and on its way out, this middleware decorates it with headers.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->addHeader('X-Frame-Options', 'DENY');
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        $response->addHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->addHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline'"); // 'unsafe-inline' often needed for basic CSS/JS unless strictly extracted
        $response->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
