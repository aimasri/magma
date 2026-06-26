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
     */
    public function push(string $queue, string $payload): void;

    /**
     * Pop a job from the front of the queue, blocking until one is available or timeout occurs.
     */
    public function pop(string $queue, int $timeout = 0): ?string;
}
