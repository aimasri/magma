<?php

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: NoCache Middleware
 *
 * Purpose:
 * - Scopes `Cache-Control` restrictions to dynamic HTML and JSON responses.
 * - Applies non-caching HTTP headers consistently across relevant outbound responses.
 *
 * Why / Why this design:
 * - Prevents stale browser states and enhances security by ensuring sensitive 
 *   dynamic pages are not cached in the browser's back/forward history.
 * - Implemented as middleware to separate HTTP transmission concerns from application logic.
 *
 * Teaching notes:
 * - This pattern prevents "Browser Back Button" data leakage for authenticated users who have just logged out.
 * - Compare to edge-caching policies in CDNs; this explicitly overrides downstream caches for dynamic routes.
 */
class NoCacheMiddleware implements MiddlewareInterface
{
    /**
     * Intercepts the outbound response to inject anti-caching headers.
     *
     * 1. Invokes the next middleware in the stack to generate the final response.
     * 2. Inspects the 'Content-Type' header of the returned response.
     * 3. Injects restrictive 'Cache-Control' and 'Pragma' headers if the content is dynamic (HTML/JSON).
     * 4. Returns the modified response back up the middleware chain.
     *
     * Logic behind the logic:
     * - Only targeting HTML and JSON prevents unnecessary caching restrictions on static assets (like CSS/JS),
     *   maintaining optimal bandwidth and loading performance for static resources while protecting sensitive dynamic data.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->getHeaders();
        $contentType = $headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/json')) {
            $response->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->addHeader('Pragma', 'no-cache');
        }

        return $response;
    }
}
