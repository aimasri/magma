<?php

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * NoCache Middleware
 *
 * Purpose:
 * - Scopes `Cache-Control` restrictions to dynamic HTML and JSON responses.
 *
 * Why / Why this design:
 * - Prevents stale browser states and enhances security by ensuring sensitive 
 *   dynamic pages are not cached in the browser's back/forward history.
 */
class NoCacheMiddleware implements MiddlewareInterface
{
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
