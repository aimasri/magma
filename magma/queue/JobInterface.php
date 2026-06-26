<?php

namespace Magma\queue;

/**
 * Job Interface
 *
 * Purpose:
 * - Define the contract for executable background jobs.
 *
 * Why / Why this design:
 * - Adheres to the Strategy Pattern and the Open/Closed Principle. Instead of the 
 *   worker daemon containing a massive `switch/case` statement for every possible 
 *   job type, the worker simply resolves a class that implements this interface and 
 *   calls `handle()`. To add a new background task, you just create a new class.
 *
 * Teaching notes:
 * - By enforcing that every job implements a generic `handle()` method, we guarantee 
 *   that the infrastructure layer (the queue worker) never needs to know the specific 
 *   business logic of the task it is executing. This is a classic example of loose coupling.
 */
interface JobInterface
{
    public const HANDLER_KEY = 'handler';
    public const PAYLOAD_KEY = 'payload';

    /**
     * Executes the background job logic.
     *
     * @param array $payload The JSON-decoded payload from the queue.
     */
    public function handle(array $payload): void;
}
