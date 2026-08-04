<?php

namespace Magma\queue;

use Redis;

/**
 * Redis Queue
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
class RedisQueue implements QueueInterface
{
    private Redis $redis;
    private string $prefix = 'queue:';

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
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
     */
    public function push(string $queue, string $payload): void
    {
        $this->redis->rpush($this->prefix . $queue, $payload);
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
        $result = $this->redis->blpop([$this->prefix . $queue], $timeout);
        
        // blpop returns an array: [0 => list_name, 1 => payload] or empty array on timeout/failure
        if (is_array($result) && isset($result[1])) {
            return $result[1];
        }

        return null;
    }

    /**
     * Pushes multiple job payloads onto the right side (tail) of the Redis list in a single batch.
     *
     * Execution Flow:
     * 1. Prepend the queue name with the environment prefix.
     * 2. Execute `RPUSH` with variadic unpacking to append all payloads atomically.
     */
    public function pushBatch(string $queue, array $payloads): void
    {
        if (empty($payloads)) {
            return;
        }
        $this->redis->rpush($this->prefix . $queue, ...$payloads);
    }
}
