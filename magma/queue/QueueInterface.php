<?php

namespace Magma\queue;

/**
 * Queue Interface
 *
 * Purpose:
 * - Define the contract for pushing and popping background jobs.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle: The application logic (like PasswordResetService) 
 *   should only depend on this interface, never on Redis natively. This allows us to 
 *   easily swap to RabbitMQ or Amazon SQS in the future without changing the app logic.
 *
 * Teaching notes:
 * - Notice how the `timeout` parameter allows blocking pops. This is a crucial concept 
 *   in high-performance messaging systems to prevent CPU-intensive busy-waiting loops.
 */
interface QueueInterface
{
    /**
     * Push a job onto the end of the queue.
     *
     * Purpose:
     * - Enqueues a payload for asynchronous processing.
     *
     * Logic behind the logic:
     * - The serialization logic is pushed down to the concrete QueueInterface implementation
     *   (e.g., RedisQueue) to decouple domain services from queue internals.
     *
     * @param string $queue
     * @param string $handlerClass
     * @param array $payload
     */
    public function push(string $queue, string $handlerClass, array $payload): void;

    /**
     * Pop a job from the front of the queue, blocking until one is available or timeout occurs.
     *
     * Purpose:
     * - Dequeues the next available job for processing.
     *
     * Logic behind the logic:
     * - The `$timeout` parameter fundamentally shifts the paradigm from aggressive polling 
     *   (which destroys CPU) to efficient connection blocking, enabling high-throughput worker daemons.
     *
     * @param string $queue
     * @param int $timeout
     * @return string|null
     */
    public function pop(string $queue, int $timeout = 0): ?string;

    /**
     * Push multiple jobs onto the end of the queue in a single batch.
     *
     * Purpose:
     * - Reduces network round trips and prevents N+1 problems when queuing many jobs at once.
     *
     * @param string $queue
     * @param string $handlerClass
     * @param array[] $payloads
     */
    public function pushBatch(string $queue, string $handlerClass, array $payloads): void;
}
