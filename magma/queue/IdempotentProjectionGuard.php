<?php

declare(strict_types=1);

namespace Magma\queue;

use Magma\database\DatabaseConnectionManager;
use PDO;
use Throwable;

/**
 * Title: Idempotent Event Projection Guard
 *
 * Purpose:
 * - Prevents duplicate or out-of-order execution of event projections, CQRS read-model updates,
 *   and outbox consumer jobs by maintaining an atomic checkpoint ledger.
 *
 * Why / Why this design:
 * - At-Least-Once Delivery Safety: Distributed message queues can deliver the same message more than once.
 *   This guard guarantees that projection handlers remain strictly idempotent.
 * - Anti-Double-Counting: In event-sourced financial or inventory ledgers, applying an event twice
 *   corrupts materialized read views. This guard provides a transactional barrier against double application.
 *
 * Teaching notes:
 * - The guard uses PostgreSQL's composite primary key (`projection_name`, `event_id`) on the
 *   `projection_checkpoints` table with atomic `INSERT ... ON CONFLICT DO NOTHING` logic to achieve
 *   distributed locking and idempotency without relying on external cache locks.
 */
class IdempotentProjectionGuard
{
    private DatabaseConnectionManager $dbManager;
    private \Magma\contracts\ClockInterface $clock;

    /**
     * Initializes the projection guard with the database connection manager.
     *
     * Logic behind the logic:
     * - The DatabaseConnectionManager is required to access the write connection directly,
     *   ensuring immediate read-after-write consistency for idempotent checks.
     *
     * @param DatabaseConnectionManager $dbManager
     * @param \Magma\contracts\ClockInterface $clock
     */
    public function __construct(DatabaseConnectionManager $dbManager, \Magma\contracts\ClockInterface $clock)
    {
        $this->dbManager = $dbManager;
        $this->clock = $clock;
    }

    /**
     * Returns the clock instance associated with this projection guard.
     *
     * @return \Magma\contracts\ClockInterface
     */
    public function getClock(): \Magma\contracts\ClockInterface
    {
        return $this->clock;
    }

    /**
     * Checks whether an event has already been successfully applied to a named projection.
     *
     * @param string $projectionName The unique identifier of the read-model projection.
     * @param string $eventId The unique identifier of the domain event or message.
     * @param int|null $tenantId Optional tenant context ID (defaults to 0 for global/system).
     * @return bool True if already processed and completed; false otherwise.
     */
    public function isProcessed(string $projectionName, string $eventId, ?int $tenantId = null): bool
    {
        $pdo = $this->dbManager->getWriteConnection();
        $tid = $tenantId ?? 0;

        $sql = 'SELECT 1 FROM "projection_checkpoints" '
             . 'WHERE "tenant_id" = :tenant_id AND "projection_name" = :projection_name AND "event_id" = :event_id AND "status" = \'completed\' '
             . 'LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Atomically claims an event execution reservation.
     *
     * Execution Flow:
     * 1. Issue an INSERT targeting `projection_checkpoints` with status 'in_flight'.
     * 2. On conflict on (tenant_id, projection_name, event_id), update status to 'in_flight' ONLY if
     *    the previous execution failed or timed out (> 5 minutes ago).
     * 3. Return true if a row was returned (claim acquired); false if already active or completed.
     *
     * @param string $projectionName
     * @param string $eventId
     * @param int|null $tenantId
     * @param array<string, mixed> $metadata
     * @return bool True if claimed successfully; false otherwise.
     */
    public function claim(string $projectionName, string $eventId, ?int $tenantId = null, array $metadata = []): bool
    {
        $pdo = $this->dbManager->getWriteConnection();
        $tid = $tenantId ?? 0;
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $staleThreshold = $this->clock->now()->modify('-5 minutes')->format('Y-m-d H:i:s');

        $sql = 'INSERT INTO "projection_checkpoints" '
             . '("tenant_id", "projection_name", "event_id", "status", "applied_at", "metadata") '
             . 'VALUES (:tenant_id, :projection_name, :event_id, \'in_flight\', :now, :metadata) '
             . 'ON CONFLICT ("tenant_id", "projection_name", "event_id") DO UPDATE '
             . 'SET "status" = \'in_flight\', "applied_at" = :now_update, "metadata" = :metadata_update '
             . 'WHERE "projection_checkpoints"."status" = \'failed\' '
             . '   OR ("projection_checkpoints"."status" = \'in_flight\' AND "projection_checkpoints"."applied_at" < :stale_threshold) '
             . 'RETURNING 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':now_update', $now);
        $stmt->bindValue(':stale_threshold', $staleThreshold);
        $metaJson = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $stmt->bindValue(':metadata', $metaJson);
        $stmt->bindValue(':metadata_update', $metaJson);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Marks an in-flight checkpoint as completed.
     *
     * @param string $projectionName
     * @param string $eventId
     * @param int|null $tenantId
     * @param array<string, mixed> $metadata
     */
    public function complete(string $projectionName, string $eventId, ?int $tenantId = null, array $metadata = []): void
    {
        $pdo = $this->dbManager->getWriteConnection();
        $tid = $tenantId ?? 0;
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $sql = 'UPDATE "projection_checkpoints" '
             . 'SET "status" = \'completed\', "applied_at" = :now, "metadata" = :metadata '
             . 'WHERE "tenant_id" = :tenant_id AND "projection_name" = :projection_name AND "event_id" = :event_id';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':metadata', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $stmt->execute();
    }

    /**
     * Marks an in-flight checkpoint as failed to permit future retries.
     *
     * @param string $projectionName
     * @param string $eventId
     * @param int|null $tenantId
     * @param string $errorMessage
     */
    public function fail(string $projectionName, string $eventId, ?int $tenantId = null, string $errorMessage = ''): void
    {
        $pdo = $this->dbManager->getWriteConnection();
        $tid = $tenantId ?? 0;
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $sql = 'UPDATE "projection_checkpoints" '
             . 'SET "status" = \'failed\', "applied_at" = :now, '
             . '    "metadata" = jsonb_set("metadata", \'{last_error}\', to_jsonb(:error::text), true) '
             . 'WHERE "tenant_id" = :tenant_id AND "projection_name" = :projection_name AND "event_id" = :event_id';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tid, PDO::PARAM_INT);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':error', mb_substr($errorMessage, 0, 1000));
        $stmt->execute();
    }

    /**
     * Guards the execution of a projection mutation using two-phase atomic claiming.
     *
     * @param string $projectionName The target projection identifier.
     * @param string $eventId The unique domain event ID.
     * @param callable $action The projection mutation callback to execute.
     * @param int|null $tenantId Optional tenant context ID.
     * @param array<string, mixed> $metadata Optional audit metadata.
     * @return mixed The callback execution result, or null if skipped due to idempotency.
     * @throws Throwable If the projection callback throws an unhandled exception.
     */
    public function guard(
        string $projectionName,
        string $eventId,
        callable $action,
        ?int $tenantId = null,
        array $metadata = []
    ): mixed {
        if (!$this->claim($projectionName, $eventId, $tenantId, $metadata)) {
            return null;
        }

        try {
            $result = $action();
            $this->complete($projectionName, $eventId, $tenantId, $metadata);
            return $result;
        } catch (Throwable $e) {
            try {
                $this->fail($projectionName, $eventId, $tenantId, $e->getMessage());
            } catch (Throwable) {
                // Suppress secondary connection/aborted transaction errors (e.g. 25P02) to preserve root exception $e
            }
            throw $e;
        }
    }

    /**
     * Guards the execution of external I/O (e.g. sending emails or webhook calls),
     * ensuring atomic claiming without wrapping in a long-lived database transaction.
     *
     * @param string $projectionName The target projection identifier.
     * @param string $eventId The unique domain event ID.
     * @param callable $action The external action callback to execute.
     * @param int|null $tenantId Optional tenant context ID.
     * @param array<string, mixed> $metadata Optional audit metadata.
     * @return mixed The callback execution result, or null if skipped due to idempotency.
     * @throws Throwable If the external action callback throws an unhandled exception.
     */
    public function guardExternalIo(
        string $projectionName,
        string $eventId,
        callable $action,
        ?int $tenantId = null,
        array $metadata = []
    ): mixed {
        if (!$this->claim($projectionName, $eventId, $tenantId, $metadata)) {
            return null;
        }

        try {
            $result = $action();
            $this->complete($projectionName, $eventId, $tenantId, $metadata);
            return $result;
        } catch (Throwable $e) {
            try {
                $this->fail($projectionName, $eventId, $tenantId, $e->getMessage());
            } catch (Throwable) {
                // Suppress secondary connection/aborted transaction errors (e.g. 25P02) to preserve root exception $e
            }
            throw $e;
        }
    }

    /**
     * Prunes expired completed and failed projection checkpoints older than a given retention threshold.
     *
     * Execution Flow:
     * 1. Acquire the write connection from DatabaseConnectionManager.
     * 2. Delete completed and failed checkpoints where applied_at is older than $retentionDays.
     * 3. Return the number of deleted checkpoint records.
     *
     * Logic behind the logic:
     * - Checkpoints for events that completed beyond the queue retry/redelivery window (e.g. 90 days)
     *   no longer need in-memory or B-tree index representation. Pruning them prevents unbounded
     *   table bloat and keeps the index hot in PostgreSQL RAM buffers.
     *
     * @param int $retentionDays Retention threshold in days (defaults to 90).
     * @return int Number of deleted checkpoint records.
     */
    public function prune(int $retentionDays = 90): int
    {
        $retentionDays = max(1, $retentionDays);
        $pdo = $this->dbManager->getWriteConnection();
        $cutoff = $this->clock->now()->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        $sql = 'DELETE FROM "projection_checkpoints" '
             . 'WHERE "status" IN (\'completed\', \'failed\') '
             . '  AND "applied_at" < :cutoff';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
