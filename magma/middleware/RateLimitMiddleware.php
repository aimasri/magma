<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\security\RateLimiterInterface;

/**
 * Title: Rate Limit Middleware
 *
 * Purpose:
 * - Intercept incoming HTTP requests to protect vulnerable endpoints.
 * - Enforce a maximum number of requests per IP address within a specific timeframe.
 *
 * Why / Why this design:
 * - Implements the Middleware / Chain of Responsibility pattern. By placing this layer 
 *   *before* the controller, we prevent malicious traffic from ever triggering expensive 
 *   database queries or business logic.
 *
 * Teaching notes:
 * - In enterprise APIs, rate limiting is often handled at the infrastructure layer 
 *   (e.g., AWS API Gateway, Cloudflare, or NGINX). Handling it at the application layer 
 *   is still necessary for fine-grained, route-specific logic (like login brute-forcing).
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterInterface $limiter;
    private int $maxAttempts = 5;
    private int $decaySeconds = 60; // 1 minute

    /**
     * Initializes the middleware with the required rate limiter instance.
     *
     * Logic behind the logic:
     * - Utilizing the `RateLimiterInterface` adheres to the Dependency Inversion Principle, 
     *   meaning this middleware doesn't care if the backend is Redis, APCu, or a database.
     */
    public function __construct(RateLimiterInterface $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Processes the incoming request and applies rate limiting rules.
     *
     * Execution Flow:
     * 1. Extract the client's IP address from the incoming Request.
     * 2. Ask the RateLimiterInterface if the IP has exceeded the allowed limit.
     * 3. If exceeded, return a 429 Too Many Requests response immediately, breaking the chain.
     * 4. Otherwise, register a "hit" in the rate limiter.
     * 5. Pass the request to the next middleware or controller via the `$next` callable.
     *
     * Logic behind the logic:
     * - We return an HTTP 429 status code instead of a 403 Forbidden because a 429 correctly 
     *   communicates to standard HTTP clients that they should simply "back off and try again later".
     */
    public function process(Request $request, callable $next): Response
    {
        // Extract IP address; falling back to a default string if unavailable
        $remoteAddr = $request->server('REMOTE_ADDR');
        $ip = is_scalar($remoteAddr) ? (string)$remoteAddr : '0.0.0.0';
        $requestUri = $request->server('REQUEST_URI');
        $uri = is_scalar($requestUri) ? (string)$requestUri : '/';
        $key = $ip . ':' . $uri;

        // Record the attempt atomically FIRST to close the race window
        // The limiter returns the new count, saving us a second GET trip.
        $currentAttempts = $this->limiter->hit($key, $this->decaySeconds);

        if ($currentAttempts > $this->maxAttempts) {
            // Threshold exceeded
            return new Response("Too Many Requests. Please try again later.", 429);
        }

        return $next($request);
    }
}
