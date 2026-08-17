<?php

namespace Magma\security;

/**
 * Title: Rate Limiter Contract
 *
 * Purpose:
 * - Define a uniform interface for throttling requests based on a specific key (e.g., an IP address).
 * - Adhere to the Dependency Inversion Principle so the application is not tightly coupled to a single store (like Redis).
 *
 * Why / Why this design:
 * - By abstracting the rate limiter into an interface, we can easily swap the backend 
 *   (e.g., APCu, Redis, Memcached, or even a SQL database) without changing any middleware logic.
 *
 * Teaching notes:
 * - Frameworks like Laravel use a similar `RateLimiter` facade backed by a driver-based architecture.
 *   This interface mimics that robust, scalable design.
 */
interface RateLimiterInterface
{
    /**
     * Determine if the key has exceeded the maximum number of attempts.
     *
     * Logic behind the logic:
     * - This operates as a simple boolean check, separating the "reading" of the state 
     *   from the "writing/incrementing" of the state (Command Query Separation).
     *
     * @param string $key The unique identifier (e.g., IP address).
     * @param int $maxAttempts The maximum allowed attempts within the decay window.
     * @return bool True if the limit is exceeded, false otherwise.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for a given key.
     *
     * Logic behind the logic:
     * - We pass `$decaySeconds` here so the implementation can set the expiration 
     *   TTL simultaneously with the increment operation.
     *
     * @param string $key The unique identifier (e.g., IP address).
     * @param int $decaySeconds The number of seconds until the rate limit resets.
     * @return int The current number of attempts after incrementing.
     */
    public function hit(string $key, int $decaySeconds): int;

    /**
     * Clear the counter for a given key (e.g., upon successful login).
     *
     * Logic behind the logic:
     * - Essential for resetting the user's attempt count if they successfully authenticate 
     *   before hitting the threshold, preventing unfair lockouts.
     *
     * @param string $key The unique identifier.
     */
    public function clear(string $key): void;
}
