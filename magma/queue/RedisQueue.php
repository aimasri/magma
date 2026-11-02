<?php

declare(strict_types=1);

namespace Magma\queue;

use Redis;
use RedisException;
use Magma\queue\JobInterface;

/**
 * Title: Redis Queue
 *
 * Purpose:
 * - A concrete implementation of QueueInterface utilizing Redis Lists.
 *
 * Why / Why this design:
 * - Utilizing native Redis commands like `RPUSH` and `BLPOP` provides an extremely 
 *   fast and memory-efficient queuing mechanism without requiring bulky external libraries.
 *
 * Teaching notes:
 * - Notice the use of a `$prefix`. In production environments, multiple applications 
 *   or environments (staging vs production) might share a single Redis instance. Prefixing 
 *   keys (e.g., `queue:emails`) prevents disastrous cross-contamination.
 */
class RedisQueue implements ReliableQueueInterface
{
    private Redis $redis;
    private string $prefix = 'queue:';

    /**
     * Initializes the Redis queue driver.
     *
     * Logic behind the logic:
     * - Injects a connected Redis instance to handle fast, memory-efficient push/pop operations.
     * - Configurable prefix prevents cross-application key collision on shared Redis servers.
     *
     * @param Redis $redis
     * @param string $prefix
     */
    public function __construct(Redis $redis, string $prefix = 'queue:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * Pushes a job payload onto the right side (tail) of the Redis list.
     *
     * Execution Flow:
     * 1. Prepend the queue name with the environment prefix.
     * 2. Execute `RPUSH` to atomically append the payload to the list.
     *
     * Logic behind the logic:
     * - `RPUSH` is an O(1) operation. It allows the web server to instantly offload the job 
     *   and immediately return an HTTP response to the user.
     * 
     * @param array<string, mixed> $payload
     */
    public function push(string $queue, string $handlerClass, array $payload): void
    {
        $data = json_encode([
            JobInterface::HANDLER_KEY => $handlerClass,
            JobInterface::PAYLOAD_KEY => $payload
        ], JSON_THROW_ON_ERROR);
        $result = $this->redis->rpush($this->prefix . $queue, $data);
        if ($result === false) {
            throw new \Magma\infrastructure\exceptions\InfrastructureException("Redis queue push failed for queue: {$queue}");
        }
    }

    /**
     * Pops a job payload from the left side (head) of the Redis list.
     *
     * Execution Flow:
     * 1. Execute `BLPOP`, blocking the connection until a new item arrives or timeout is reached.
     * 2. Extract the payload string from the Redis array response.
     *
     * Logic behind the logic:
     * - `BLPOP` is a blocking pop. Instead of writing a "while(true)" loop in PHP that 
     *   constantly hammers the Redis server with `LPOP` polling (which spikes CPU usage to 100%), 
     *   `BLPOP` tells the Redis server to put the PHP connection to sleep until a message 
     *   arrives, saving massive amounts of CPU cycles.
     */
    public function pop(string $queue, int $timeout = 0): ?string
    {
        try {
            if ($timeout <= 0) {
                $result = $this->redis->lpop($this->prefix . $queue);
                return $result !== false ? (string)$result : null;
            }

            $result = $this->redis->blpop([$this->prefix . $queue], $timeout);
            
            // blpop returns an array: [0 => list_name, 1 => payload] or empty array on timeout/failure
            if (is_array($result) && isset($result[1]) && is_scalar($result[1])) {
                return (string) $result[1];
            }

            return null;
        } catch (RedisException $e) {
            // Attempt to test socket viability; re-throw if unrecoverable so daemon can back off cleanly
            try {
                $this->redis->ping();
            } catch (\Throwable) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Pushes multiple job payloads onto the right side (tail) of the Redis list in a single batch.
     *
     * Execution Flow:
     * 1. Prepend the queue name with the environment prefix.
     * 2. Execute `RPUSH` with variadic unpacking to append all payloads atomically.
     * 
     * @param array<int, array<string, mixed>> $payloads
     */
    public function pushBatch(string $queue, string $handlerClass, array $payloads): void
    {
        if (empty($payloads)) {
            return;
        }
        
        $encodedPayloads = array_map(function ($payload) use ($handlerClass) {
            return json_encode([
                JobInterface::HANDLER_KEY => $handlerClass,
                JobInterface::PAYLOAD_KEY => $payload
            ], JSON_THROW_ON_ERROR);
        }, $payloads);

        $result = $this->redis->rpush($this->prefix . $queue, ...$encodedPayloads);
        if ($result === false) {
            throw new \Magma\infrastructure\exceptions\InfrastructureException("Redis queue batch push failed for queue: {$queue}");
        }
    }

    /**
     * @inheritDoc
     */
    public function getQueueLength(string $queue): int
    {
        try {
            return (int) $this->redis->llen($this->prefix . $queue);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function pushDelayed(string $queue, string $handlerClass, array $payload, int $delaySeconds): void
    {
        $data = json_encode([
            JobInterface::HANDLER_KEY => $handlerClass,
            JobInterface::PAYLOAD_KEY => $payload
        ], JSON_THROW_ON_ERROR);
        
        $readyAt = time() + max(0, $delaySeconds);
        $result = $this->redis->zadd($this->prefix . $queue . ':delayed', $readyAt, $data);
        if ($result === false) {
            throw new \Magma\infrastructure\exceptions\InfrastructureException("Redis queue pushDelayed failed for queue: {$queue}");
        }
    }

    /**
     * @inheritDoc
     */
    public function migrateDelayed(string $queue): int
    {
        $delayedKey = $this->prefix . $queue . ':delayed';
        $activeKey = $this->prefix . $queue;
        $now = time();

        $lua = "
            local delayedKey = KEYS[1]
            local activeKey = KEYS[2]
            local now = tonumber(ARGV[1])
            local items = redis.call('ZRANGEBYSCORE', delayedKey, 0, now, 'LIMIT', 0, 100)
            local count = 0
            if items and #items > 0 then
                for _, item in ipairs(items) do
                    redis.call('RPUSH', activeKey, item)
                    redis.call('ZREM', delayedKey, item)
                    count = count + 1
                end
            end
            return count
        ";

        try {
            $migrated = $this->redis->eval($lua, [$delayedKey, $activeKey, $now], 2);
            return is_numeric($migrated) ? (int) $migrated : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function popReliable(string $queue, int $timeout = 0): ?string
    {
        $this->migrateDelayed($queue);

        $sourceKey = $this->prefix . $queue;
        $processingKey = $this->prefix . $queue . ':processing';

        try {
            // Modern Redis 6.2+ atomic pop from head of active to tail of processing
            if (method_exists($this->redis, 'blmove')) {
                $result = $this->redis->blmove($sourceKey, $processingKey, 'LEFT', 'RIGHT', $timeout);
                if (is_string($result) && $result !== '') {
                    return $result;
                }
            } else {
                // Fallback to classic BRPOPLPUSH across older Redis engines
                $result = $this->redis->brpoplpush($sourceKey, $processingKey, $timeout);
                if (is_string($result) && $result !== '') {
                    return $result;
                }
            }

            return null;
        } catch (RedisException $e) {
            try {
                $this->redis->ping();
            } catch (\Throwable) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function acknowledge(string $queue, string $jobString): void
    {
        $processingKey = $this->prefix . $queue . ':processing';
        try {
            $this->redis->lrem($processingKey, $jobString, 1);
        } catch (\Throwable) {
            // Suppress secondary cleanup exceptions on socket blips
        }
    }

    /**
     * @inheritDoc
     */
    public function recoverOrphaned(string $queue): int
    {
        $processingKey = $this->prefix . $queue . ':processing';
        $activeKey = $this->prefix . $queue;

        $lua = "
            local procKey = KEYS[1]
            local activeKey = KEYS[2]
            local items = redis.call('LRANGE', procKey, 0, -1)
            local count = 0
            if items and #items > 0 then
                for _, item in ipairs(items) do
                    redis.call('RPUSH', activeKey, item)
                    count = count + 1
                end
                redis.call('DEL', procKey)
            end
            return count
        ";

        try {
            $recovered = $this->redis->eval($lua, [$processingKey, $activeKey], 2);
            return is_numeric($recovered) ? (int) $recovered : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}

