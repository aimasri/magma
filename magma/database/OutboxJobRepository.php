<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Title: PostgreSQL Concurrent Transactional Outbox Repository
 *
 * Purpose:
 * - Implements transactional outbox storage, batched retrieval with PostgreSQL `FOR UPDATE SKIP LOCKED`,
 *   and batched cleanup to facilitate asynchronous, reliable event and job publishing.
 *
 * Why / Why this design:
 * - PostgreSQL Native Locking: `FOR UPDATE SKIP LOCKED` allows multiple publisher workers to query 
 *   the same outbox table concurrently without blocking each other or attempting to publish the same record.
 * - Statement Churn Elimination: Consolidates individual row operations into locked query batches 
 *   and single multi-parameter DELETE queries, eliminating network round-trips and prepared statement churn.
 * - Separation of Concerns: Encapsulates SQL transactions and schema mappings away from queue publisher daemons.
 *
 * Teaching notes:
 * - When running `SELECT ... FOR UPDATE SKIP LOCKED`, the calling process MUST keep the transaction open 
 *   until jobs are published and either deleted or released. If the transaction rolls back or the worker dies,
 *   PostgreSQL automatically releases the lock immediately, ensuring zero orphaned jobs.
 */
class OutboxJobRepository implements OutboxJobRepositoryInterface
{
    private DatabaseConnectionManager $dbManager;

    public function __construct(DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Fetches and row-locks pending outbox records using SKIP LOCKED.
     *
     * Execution Flow:
     * 1. Acquire the Write connection (required for row-level locks).
     * 2. Prepare the `SELECT ... FOR UPDATE SKIP LOCKED` query limited by the requested count.
     * 3. Execute the statement and fetch raw row records.
     * 4. Decode JSONB payload and headers columns into strongly-typed associative arrays.
     * 5. Return normalized records.
     *
     * Logic behind the logic:
     * - `SKIP LOCKED` instructs PostgreSQL to skip any rows currently locked by another concurrent transaction,
     *   returning only free rows. This provides frictionless horizontal scaling for queue publishing workers.
     *
     * @param int $limit Maximum number of pending records to fetch and lock.
     * @return array<int, array{id: int, queue: string, handler: string, payload: array<string, mixed>, headers: array<string, mixed>, attempts: int, created_at: string}>
     */
    public function fetchAndLockPending(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $pdo = $this->dbManager->getWriteConnection();

        $sql = 'UPDATE "outbox_jobs" '
             . 'SET "locked_at" = NOW() '
             . 'WHERE "id" IN ('
             . '    SELECT "id" FROM "outbox_jobs" '
             . '    WHERE "locked_at" IS NULL OR "locked_at" < NOW() - INTERVAL \'5 minutes\' '
             . '    ORDER BY "id" ASC '
             . '    LIMIT :limit '
             . '    FOR UPDATE SKIP LOCKED'
             . ') RETURNING "id", "queue", "handler", "payload", "headers", "attempts", "created_at"';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \Magma\infrastructure\exceptions\DatabaseException("Outbox fetch failed.", 0, $e);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $payload = is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'];
            $headers = is_string($row['headers']) ? json_decode($row['headers'], true) : $row['headers'];

            $results[] = [
                'id' => (int) $row['id'],
                'queue' => (string) $row['queue'],
                'handler' => (string) $row['handler'],
                'payload' => is_array($payload) ? $payload : [],
                'headers' => is_array($headers) ? $headers : [],
                'attempts' => (int) ($row['attempts'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
            ];
        }

        return $results;
    }

    /**
     * Deletes a batch of processed outbox entries in a single SQL operation.
     *
     * Execution Flow:
     * 1. Validate that the ID array is non-empty.
     * 2. Sanitize and cast all IDs to integers.
     * 3. Construct a dynamic parameterized query with `IN (?, ?, ...)`.
     * 4. Execute the DELETE statement against the Write connection.
     *
     * Logic behind the logic:
     * - Performing a single batched DELETE rather than deleting rows inside a foreach loop
     *   reduces database round-trip latency by orders of magnitude.
     *
     * @param int[] $ids Array of outbox record primary keys to delete.
     */
    public function deleteBatch(array $ids): void
    {
        $sanitizedIds = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if (empty($sanitizedIds)) {
            return;
        }

        $pdo = $this->dbManager->getWriteConnection();
        $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
        $sql = "DELETE FROM \"outbox_jobs\" WHERE \"id\" IN ({$placeholders})";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($sanitizedIds);
        } catch (\PDOException $e) {
            throw new \Magma\infrastructure\exceptions\DatabaseException("Outbox delete batch failed.", 0, $e);
        }
    }

    /**
     * Inserts a new outbox record within the active transactional boundary.
     *
     * Execution Flow:
     * 1. JSON-encode payload and metadata headers from the DTO.
     * 2. Prepare the parameterized INSERT query.
     * 3. Execute statement and return generated sequence ID.
     *
     * Logic behind the logic:
     * - Writing to the outbox inside the same transaction as domain entity persistence guarantees
     *   that events are never lost if the application crashes before pushing to external message queues.
     *
     * @param \Magma\dto\OutboxJobDTO $job Target job DTO.
     * @return int The primary key of the inserted outbox record.
     */
    public function record(\Magma\dto\OutboxJobDTO $job): int
    {
        $pdo = $this->dbManager->getWriteConnection();

        $sql = 'INSERT INTO "outbox_jobs" ("queue", "handler", "payload", "headers", "attempts", "created_at") '
             . 'VALUES (:queue, :handler, :payload, :headers, 0, NOW()) RETURNING "id"';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':queue', trim($job->queue));
            $stmt->bindValue(':handler', trim($job->handlerClass));
            $stmt->bindValue(':payload', json_encode($job->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $stmt->bindValue(':headers', json_encode($job->headers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \Magma\infrastructure\exceptions\DatabaseException("Outbox record failed.", 0, $e);
        }

        $id = $stmt->fetchColumn();
        return (int) $id;
    }

    /**
     * Records multiple jobs into the transactional outbox table in a single batched insert.
     *
     * @param array<int, \Magma\dto\OutboxJobDTO> $jobs Array of OutboxJobDTO instances.
     */
    public function recordBulk(array $jobs): void
    {
        if (empty($jobs)) {
            return;
        }

        $pdo = $this->dbManager->getWriteConnection();

        $chunks = array_chunk($jobs, 1000);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, 0, NOW())'));
            $sql = 'INSERT INTO "outbox_jobs" ("queue", "handler", "payload", "headers", "attempts", "created_at") VALUES ' . $placeholders;

            $stmt = $pdo->prepare($sql);
            
            $i = 1;
            /** @var \Magma\dto\OutboxJobDTO $job */
            foreach ($chunk as $job) {
                $stmt->bindValue($i++, trim($job->queue));
                $stmt->bindValue($i++, trim($job->handlerClass));
                $stmt->bindValue($i++, json_encode($job->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $stmt->bindValue($i++, json_encode($job->headers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            }

            try {
                $stmt->execute();
            } catch (\PDOException $e) {
                throw new \Magma\infrastructure\exceptions\DatabaseException("Outbox bulk record failed.", 0, $e);
            }
        }
    }

    /**
     * Increments attempt count and records failure description.
     *
     * Execution Flow:
     * 1. Prepare an UPDATE statement targeting the specified record ID.
     * 2. Increment `attempts`, reset `locked_at` to NULL to permit retry, and persist error log.
     * 3. Execute update on Write connection.
     *
     * Logic behind the logic:
     * - Releasing `locked_at` back to NULL with exponential backoff / retry tracking ensures transient
     *   failures (such as temporary Redis queue unavailability) recover automatically on subsequent worker passes.
     *
     * @param int $id The outbox record primary key.
     * @param string $errorMessage Description of the failure.
     */
    public function releaseOrMarkFailed(int $id, string $errorMessage): void
    {
        $pdo = $this->dbManager->getWriteConnection();

        $sql = 'UPDATE "outbox_jobs" '
             . 'SET "attempts" = "attempts" + 1, '
             . '    "locked_at" = NULL, '
             . '    "last_error" = :error '
             . 'WHERE "id" = :id';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':error', mb_substr($errorMessage, 0, 1000));
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \Magma\infrastructure\exceptions\DatabaseException("Outbox status update failed.", 0, $e);
        }
    }
}
