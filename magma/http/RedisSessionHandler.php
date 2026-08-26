<?php

namespace Magma\http;

use Redis;
use SessionHandlerInterface;

/**
 * Title: Redis Session Handler
 *
 * Purpose:
 * - Implements PHP's native SessionHandlerInterface to store session data in a Redis cluster.
 * - Replaces local file-based sessions to enable horizontal scaling across multiple servers.
 *
 * Why this design:
 * - Instead of relying on `ini_set('session.save_handler', 'redis')` which relies on C-extension magic, building an explicit handler adheres to our "no magic" philosophy.
 * - Adheres strictly to the Single Responsibility Principle: This class ONLY handles writing/reading from Redis up to a maximum absolute TTL. Business-level timeouts are handled separately in the Middleware layer.
 *
 * Teaching notes:
 * - When PHP calls `session_start()`, it triggers `read()`. When the script shuts down, PHP automatically triggers `write()`. 
 */
class RedisSessionHandler implements SessionHandlerInterface
{
    private Redis $redis;
    private int $maxTtl;
    private string $prefix = 'session:';

    /**
     * Initializes the handler with Redis and an absolute maximum TTL.
     *
     * Logic behind the logic:
     * - Dependency Injection guarantees the Redis connection is ready before PHP ever asks the handler to do anything.
     */
    public function __construct(Redis $redis, int $maxTtl = 7200)
    {
        $this->redis = $redis;
        $this->maxTtl = $maxTtl;
    }

    /**
     * Opens the session storage mechanism.
     * 
     * Logic behind the logic:
     * - Always returns true as the connection is already established via Dependency Injection in the constructor.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Closes the session storage mechanism.
     * 
     * Logic behind the logic:
     * - Simply returns true as the Redis connection lifecycle is managed outside this class.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Reads the session data from Redis.
     *
     * Execution Flow:
     * 1. Query Redis for the prefixed session ID.
     * 2. Return the serialized data string, or an empty string if it doesn't exist.
     *
     * Logic behind the logic:
     * - A fast O(1) memory lookup ensuring practically zero overhead during the critical bootstrapping phase of the request lifecycle.
     */
    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        return is_string($data) ? $data : '';
    }

    /**
     * Writes the session data to Redis with the absolute maximum TTL.
     *
     * Execution Flow:
     * 1. Execute `SETEX` to store the data and set the expiration timer natively.
     *
     * Logic behind the logic:
     * - Using `SETEX` securely packages the store action and the TTL expiration into a single atomic network call, preventing orphan keys in the event of a network blip.
     */
    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->maxTtl, $data);
    }

    /**
     * Deletes the session from Redis.
     *
     * Logic behind the logic:
     * - Instantly freeing the memory in Redis rather than waiting for the TTL to naturally expire.
     */
    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    /**
     * Garbage collection.
     *
     * Logic behind the logic:
     * - Redis natively handles key expiration via the TTL set in `write()`. Therefore, PHP does not need to perform expensive periodic garbage collection sweeps.
     */
    public function gc(int $max_lifetime): int|false
    {
        return 1; // 1 means success in PHP 8+
    }
}
