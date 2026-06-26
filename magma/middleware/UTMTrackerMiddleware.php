<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;

/**
 * UTMTrackerMiddleware — captures UTM parameters and persists attribution.
 *
 * Purpose:
 * - Store common UTM parameters in session state so marketing attribution
 *   survives navigation and contributes to analytics or campaign tracking.
 * 
 * Why / Why this design:
 * - Implements the Intercepting Filter pattern via the Middleware layer. By extracting 
 *   marketing tracking into middleware, controllers remain completely unaware of 
 *   analytics logic, preserving the Single Responsibility Principle.
 * 
 * Teaching notes:
 * - Global middleware like this runs on every single request. Ensure it remains 
 *   highly performant and avoids unnecessary database reads.
 */
class UTMTrackerMiddleware implements MiddlewareInterface
{
    /**
     * Executes the middleware layer.
     * 
     * Execution Flow:
     * 1. Define the whitelist of accepted UTM query parameters.
     * 2. Extract any present UTM parameters from the HTTP request query string.
     * 3. If any parameters are found, store them in the session for long-term attribution.
     * 4. Also attach them directly to the request attributes for immediate use in the current cycle.
     * 5. Pass the request to the next middleware in the pipeline.
     * 
     * Logic behind the logic:
     * - Storing in both the session and request attributes ensures the data is available 
     *   both asynchronously (future requests) and synchronously (this exact request).
     */
    public function process(Request $request, callable $next): Response
    {
        $utmParams = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ];

        $tracked = [];
        foreach ($utmParams as $param) {
            $value = $request->query($param);
            if ($value !== null) {
                // Neutralize XSS payloads by escaping HTML special characters
                $tracked[$param] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!empty($tracked)) {
            $request->setSession('_utm_tracking', $tracked);
            $request->setAttribute('utm_data', $tracked);
        }

        return $next($request);
    }
}
