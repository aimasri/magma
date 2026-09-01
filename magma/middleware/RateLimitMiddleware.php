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
    private int $maxAttempts = 30;
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
     * Configures the rate limits for the middleware instance.
     * 
     * @param int $maxAttempts Maximum number of allowed attempts
     * @param int $decaySeconds Time window in seconds
     */
    public function configure(int $maxAttempts, int $decaySeconds): void
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
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
        // Extract IP address from trusted proxies or direct connection
        $ip = $this->resolveClientIp($request);

        if ($ip === null) {
            return new Response("Unable to identify client IP. Request rejected.", 429);
        }

        $requestUri = $request->server('REQUEST_URI');
        $uri = is_scalar($requestUri) ? (string)$requestUri : '/';
        $key = $ip . ':' . $uri;

        // Record the attempt atomically FIRST to close the race window
        // The limiter returns the new count, saving us a second GET trip.
        $currentAttempts = $this->limiter->hit($key, $this->decaySeconds);
        $remaining = max(0, $this->maxAttempts - $currentAttempts);

        if ($currentAttempts > $this->maxAttempts) {
            // Threshold exceeded
            $response = new Response("Too Many Requests. Please try again later.", 429);
            $response->setHeader('Retry-After', (string)$this->decaySeconds);
            $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
            $response->setHeader('X-RateLimit-Remaining', '0');
            return $response;
        }

        /** @var Response $response */
        $response = $next($request);
        
        $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string)$remaining);

        return $response;
    }

    private function resolveClientIp(Request $request): ?string
    {
        // Check Cloudflare first
        $cfIp = $request->server('HTTP_CF_CONNECTING_IP');
        if (is_string($cfIp) && $cfIp !== '') {
            return trim($cfIp);
        }

        // Check X-Forwarded-For (can be a comma-separated list, first is original client)
        $xff = $request->server('HTTP_X_FORWARDED_FOR');
        if (is_string($xff) && $xff !== '') {
            $ips = explode(',', $xff);
            return trim($ips[0]);
        }

        // Fallback to direct remote address
        $remoteAddr = $request->server('REMOTE_ADDR');
        if (is_string($remoteAddr) && $remoteAddr !== '') {
            return trim($remoteAddr);
        }

        return null;
    }
}
