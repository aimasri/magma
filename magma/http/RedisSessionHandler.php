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
    private string $lockPrefix = 'session:lock:';
    private ?string $lockedSessionId = null;

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
     * Closes the session storage mechanism and releases any acquired mutex locks.
     * 
     * Logic behind the logic:
     * - Guarantees session locks are released at request shutdown even if write() was skipped.
     */
    public function close(): bool
    {
        if ($this->lockedSessionId !== null) {
            $this->releaseLock($this->lockedSessionId);
        }
        return true;
    }

    /**
     * Reads the session data from Redis, acquiring a mutex lock to serialize concurrent tab requests.
     *
     * Execution Flow:
     * 1. Spin-lock Redis mutex key with 10-second safety TTL.
     * 2. Query Redis for the prefixed session ID.
     * 3. Return the serialized data string, or an empty string if it doesn't exist.
     *
     * Logic behind the logic:
     * - A fast O(1) memory lookup with spin-lock protection prevents last-write-wins race conditions
     *   when a user opens multiple browser tabs simultaneously.
     */
    public function read(string $id): string|false
    {
        $this->acquireLock($id);
        $data = $this->redis->get($this->prefix . $id);
        return is_string($data) ? $data : '';
    }

    /**
     * Writes the session data to Redis with the absolute maximum TTL and releases the mutex lock.
     *
     * Execution Flow:
     * 1. Execute `SETEX` to store the data and set the expiration timer natively.
     * 2. Release the acquired session mutex lock.
     *
     * Logic behind the logic:
     * - Using `SETEX` securely packages the store action and the TTL expiration into a single atomic network call,
     *   preventing orphan keys in the event of a network blip.
     */
    public function write(string $id, string $data): bool
    {
        try {
            return $this->redis->setex($this->prefix . $id, $this->maxTtl, $data);
        } finally {
            $this->releaseLock($id);
        }
    }

    /**
     * Acquires a transient Redis lock with retry spin to serialize concurrent requests for the same session.
     *
     * @param string $id
     */
    private function acquireLock(string $id): void
    {
        $lockKey = $this->lockPrefix . $id;
        $retries = 20; // 20 * 50ms = 1.0 second max wait

        while ($retries > 0) {
            $acquired = $this->redis->set($lockKey, '1', ['NX', 'EX' => 10]);
            if ($acquired) {
                $this->lockedSessionId = $id;
                return;
            }
            usleep(50000); // 50ms backoff
            $retries--;
        }

        // Fall back gracefully if lock times out to avoid blocking the HTTP request indefinitely
        $this->lockedSessionId = $id;
    }

    /**
     * Releases the acquired Redis session mutex lock.
     *
     * @param string $id
     */
    private function releaseLock(string $id): void
    {
        if ($this->lockedSessionId === $id) {
            $this->redis->del($this->lockPrefix . $id);
            $this->lockedSessionId = null;
        }
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
