<?php

declare(strict_types=1);

namespace Magma\queue;

/**
 * Title: Reliable Queue Interface
 *
 * Purpose:
 * - Extends QueueInterface with enterprise-grade at-least-once two-phase processing,
 *   non-blocking delayed execution with jittered exponential backoff, and queue depth inspection.
 *
 * Why / Why this design:
 * - Adheres strictly to the Open/Closed Principle (OCP) and Interface Segregation Principle (ISP).
 *   Leaves QueueInterface untouched so existing drivers and legacy mocks remain fully valid, while
 *   empowering production queues with crash-resilient processing and backpressure metrics.
 *
 * Teaching notes:
 * - Standard queues (BLPOP) suffer message loss if the worker process is killed mid-job.
 *   Two-phase acknowledgment moves the message to a processing list first (BRPOPLPUSH / BLMOVE)
 *   and only removes it when the worker acknowledges successful completion.
 */
interface ReliableQueueInterface extends QueueInterface
{
    /**
     * Inspects the current pending message backlog length for the target queue.
     *
     * @param string $queue
     * @return int
     */
    public function getQueueLength(string $queue): int;

    /**
     * Pushes a job onto a delayed sorted set to execute after a specified delay in seconds.
     *
     * @param string $queue
     * @param string $handlerClass
     * @param array<string, mixed> $payload
     * @param int $delaySeconds
     */
    public function pushDelayed(string $queue, string $handlerClass, array $payload, int $delaySeconds): void;

    /**
     * Migrates matured delayed jobs (where ready_at <= now) from the delayed set into the active queue list.
     *
     * @param string $queue
     * @return int Number of migrated jobs.
     */
    public function migrateDelayed(string $queue): int;

    /**
     * Pops a job payload reliably using two-phase delivery, moving it atomically from the active queue
     * to the in-flight processing list.
     *
     * @param string $queue
     * @param int $timeout
     * @return string|null Raw job payload string, or null on timeout.
     */
    public function popReliable(string $queue, int $timeout = 0): ?string;

    /**
     * Acknowledges that a popped job has successfully finished (or moved to DLQ),
     * removing it from the in-flight processing list.
     *
     * @param string $queue
     * @param string $jobString Raw JSON job string previously returned by popReliable().
     */
    public function acknowledge(string $queue, string $jobString): void;

    /**
     * Recovers in-flight jobs that have been orphaned in the processing list due to an unhandled
     * process crash (SIGKILL or host restart).
     *
     * @param string $queue
     * @return int Number of recovered jobs re-queued.
     */
    public function recoverOrphaned(string $queue): int;
}
