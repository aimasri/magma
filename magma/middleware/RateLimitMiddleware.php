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
    private int $maxAttempts = 200;
    private int $decaySeconds = 60; // 1 minute
    private string $scope = 'route';

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
     * Configures the rate limits and scope for the middleware instance.
     * 
     * @param int $maxAttempts Maximum number of allowed attempts
     * @param int $decaySeconds Time window in seconds
     * @param string $scope Scope namespace prefix to prevent collision between global and route limiters
     */
    public function configure(int $maxAttempts, int $decaySeconds, string $scope = 'route'): void
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->scope = $scope;
    }

    /**
     * Sets the scope namespace prefix.
     *
     * @param string $scope
     * @return void
     */
    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    /**
     * Processes the incoming request and applies rate limiting rules.
     *
     * Execution Flow:
     * 1. Extract the client's IP address from the incoming Request.
     * 2. Derive rate limiting key strictly from the normalized route path, ignoring volatile query strings.
     * 3. Prefix the key with the configured scope namespace to prevent global/route collision.
     * 4. Record the attempt atomically in the limiter.
     * 5. If limit exceeded, return HTTP 429 Too Many Requests response immediately.
     * 6. Pass the request to the next middleware or controller via the `$next` callable.
     */
    public function process(Request $request, callable $next): Response
    {
        // Bypass rate limiting in development to prevent locking out developers
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            return $next($request);
        }

        // Extract IP address from trusted proxies or direct connection
        $ip = $this->resolveClientIp($request);

        if ($ip === null) {
            return new Response("Unable to identify client IP. Request rejected.", 429);
        }

        // Use getPath() rather than REQUEST_URI to eliminate query parameter bypasses
        $path = $request->getPath();
        $key = 'ratelimit:' . $this->scope . ':' . $ip . ':' . $path;

        // Record the attempt atomically FIRST to close the race window
        // The limiter returns the new count, saving us a second GET trip.
        try {
            $currentAttempts = $this->limiter->hit($key, $this->decaySeconds);
            $remaining = max(0, $this->maxAttempts - $currentAttempts);

            if ($currentAttempts > $this->maxAttempts) {
                // Threshold exceeded
                $response = new Response("Too Many Requests. Please try again later.", 429);
                $response->addHeader('Retry-After', (string)$this->decaySeconds);
                $response->addHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
                $response->addHeader('X-RateLimit-Remaining', '0');
                return $response;
            }
        } catch (\Throwable $e) {
            // Fail open if rate limiter is down (e.g., Redis failure)
            error_log(sprintf('[RateLimitMiddleware] Limiter backend failure, failing open: %s', $e->getMessage()));
            $remaining = $this->maxAttempts;
        }

        /** @var Response $response */
        $response = $next($request);
        
        $response->addHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->addHeader('X-RateLimit-Remaining', (string)$remaining);

        return $response;
    }

    private function resolveClientIp(Request $request): ?string
    {
        $remoteAddrVal = $request->server('REMOTE_ADDR');
        $remoteAddr = (is_string($remoteAddrVal) && $remoteAddrVal !== '') ? trim($remoteAddrVal) : null;

        $trustedProxies = \Magma\config\Config::get('TRUSTED_PROXIES', ['127.0.0.1']);
        if (is_string($trustedProxies)) {
            $trustedProxies = explode(',', $trustedProxies);
        }

        $isTrustedProxy = false;
        if ($remoteAddr !== null && is_array($trustedProxies)) {
            foreach ($trustedProxies as $proxy) {
                if (is_string($proxy) && str_starts_with($remoteAddr, trim($proxy))) {
                    $isTrustedProxy = true;
                    break;
                }
            }
        }

        if ($isTrustedProxy) {
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
        }

        return $remoteAddr;
    }
}
