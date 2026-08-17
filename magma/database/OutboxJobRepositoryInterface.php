<?php

declare(strict_types=1);

namespace Magma\database;

/**
 * Title: Outbox Job Repository Contract
 *
 * Purpose:
 * - Defines the contract for transactional outbox persistence, retrieval with row-level locking,
 *   and batched deletion.
 *
 * Why / Why this design:
 * - Transactional Outbox Pattern: Allows domain mutations and asynchronous message publishing 
 *   to be saved atomically in the same database transaction.
 * - Concurrency Control: Provides `fetchAndLockPending` using PostgreSQL's `FOR UPDATE SKIP LOCKED`
 *   to allow horizontal scaling of multiple publisher daemons without race conditions or deadlocks.
 *
 * Teaching notes:
 * - The Transactional Outbox pattern guarantees at-least-once delivery by writing events directly
 *   to the database within the business transaction, then publishing them via an asynchronous worker.
 */
interface OutboxJobRepositoryInterface
{
    /**
     * Fetches and locks pending outbox jobs ready for publishing.
     *
     * @param int $limit Maximum number of jobs to fetch and lock.
     * @return array<int, array{id: int, queue: string, handler: string, payload: array, headers: array, attempts: int, created_at: string}>
     */
    public function fetchAndLockPending(int $limit = 100): array;

    /**
     * Deletes a batch of successfully published outbox jobs by their IDs.
     *
     * @param int[] $ids Array of outbox record primary keys to delete.
     */
    public function deleteBatch(array $ids): void;

    /**
     * Records a new job into the transactional outbox table.
     *
     * @param string $queue Target queue name (e.g. 'emails', 'events', 'inventory').
     * @param string $handlerClass Fully qualified class name of the Job handler.
     * @param array $payload Domain data payload for the job.
     * @param array $headers Optional metadata headers (e.g. tenant_id, correlation_id, timestamp).
     * @return int The generated outbox job ID.
     */
    public function record(string $queue, string $handlerClass, array $payload, array $headers = []): int;

    /**
     * Increments attempt count and records the failure message for an outbox job.
     *
     * @param int $id The outbox record primary key.
     * @param string $errorMessage Description of the failure.
     */
    public function releaseOrMarkFailed(int $id, string $errorMessage): void;
}
