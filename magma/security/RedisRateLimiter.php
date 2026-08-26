<?php

namespace Magma\security;

use Redis;

/**
 * Title: Redis Rate Limiter Implementation
 *
 * Purpose:
 * - Provide a concrete, memory-backed implementation of the RateLimiterInterface.
 * - Manage request counters efficiently using Redis key-value storage.
 *
 * Why / Why this design:
 * - Utilizes the Adapter pattern to wrap native Redis commands (`incr`, `expire`) into 
 *   our application's specific domain logic interface (`hit`, `tooManyAttempts`).
 *
 * Teaching notes:
 * - Redis is single-threaded and processes commands atomically. This makes it mathematically 
 *   perfect for high-concurrency rate limiting where traditional database row-locking would fail.
 */
class RedisRateLimiter implements RateLimiterInterface
{
    private Redis $redis;
    private string $prefix = 'rate_limit:';

    /**
     * Initializes the rate limiter with a Redis connection.
     * 
     * Logic behind the logic:
     * - Constructor injection ensures this class is fully instantiated and ready to use,
     *   rather than creating its own connection and violating Dependency Injection.
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Checks if the maximum attempts have been reached.
     *
     * Execution Flow:
     * 1. Retrieve the current counter value from Redis using the prefixed key.
     * 2. Cast the result to an integer (Redis returns strings or false).
     * 3. Evaluate if the value meets or exceeds the maximum threshold.
     *
     * Logic behind the logic:
     * - By doing a fast O(1) GET command, we minimize overhead on every single HTTP request.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $val = $this->redis->get($this->prefix . $key);
        $attempts = is_scalar($val) ? (int)$val : 0;
        return $attempts >= $maxAttempts;
    }

    /**
     * Increments the attempt counter and sets the decay timer atomically.
     *
     * Execution Flow:
     * 1. Generate the fully qualified Redis key.
     * 2. Execute an atomic Lua script to prevent TOCTOU race conditions.
     * 3. The script executes `INCR` and captures the new total.
     * 4. If this is the very first hit (value is 1), the script executes `EXPIRE`.
     * 5. Return the new total.
     *
     * Logic behind the logic:
     * - We use a Lua script instead of separate `INCR` and `EXPIRE` commands from PHP.
     *   If the PHP process crashed between separate commands, the key would become 
     *   immortal (no TTL), permanently rate-limiting an IP. Lua scripts execute 
     *   atomically on the Redis server, guaranteeing consistency.
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $redisKey = $this->prefix . $key;
        
        $script = "
            local current = redis.call('INCR', KEYS[1])
            if current == 1 then
                redis.call('EXPIRE', KEYS[1], ARGV[1])
            end
            return current
        ";
        
        $val = $this->redis->eval($script, [$redisKey, $decaySeconds], 1);
        return is_scalar($val) ? (int)$val : 1;
    }

    /**
     * Removes the rate limit tracking key from Redis.
     *
     * Execution Flow:
     * 1. Execute the `DEL` command against the prefixed key.
     *
     * Logic behind the logic:
     * - Hard-deleting the key is more efficient than setting its value to 0, as it 
     *   frees up RAM immediately.
     */
    public function clear(string $key): void
    {
        $this->redis->del($this->prefix . $key);
    }
}
