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

    public function __construct(DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Checks whether an event has already been applied to a named projection.
     *
     * Execution Flow:
     * 1. Query the `projection_checkpoints` table on the Write connection.
     * 2. Return true if a matching row exists, false otherwise.
     *
     * Logic behind the logic:
     * - We check the Write connection to ensure immediate read-after-write consistency within
     *   active transaction boundaries, bypassing read-replica replication lag.
     *
     * @param string $projectionName The unique identifier of the read-model projection.
     * @param string $eventId The unique identifier of the domain event or message.
     * @return bool True if already processed; false otherwise.
     */
    public function isProcessed(string $projectionName, string $eventId): bool
    {
        $pdo = $this->dbManager->getWriteConnection();

        $sql = 'SELECT 1 FROM "projection_checkpoints" '
             . 'WHERE "projection_name" = :projection_name AND "event_id" = :event_id '
             . 'LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Records a projection checkpoint indicating that an event was successfully applied.
     *
     * Execution Flow:
     * 1. Prepare an INSERT query targeting `projection_checkpoints`.
     * 2. Bind the projection name, event ID, optional tenant ID, and JSON-encoded metadata.
     * 3. Execute using `ON CONFLICT ("projection_name", "event_id") DO NOTHING`.
     *
     * Logic behind the logic:
     * - `ON CONFLICT DO NOTHING` handles potential race conditions gracefully without throwing
     *   unhandled unique constraint PDOExceptions.
     *
     * @param string $projectionName The target projection identifier.
     * @param string $eventId The domain event ID.
     * @param int|null $tenantId Optional tenant context ID.
     * @param array<string, mixed> $metadata Optional audit metadata.
     * @return bool True if a new checkpoint was inserted; false if it already existed.
     */
    public function markProcessed(string $projectionName, string $eventId, ?int $tenantId = null, array $metadata = []): bool
    {
        $pdo = $this->dbManager->getWriteConnection();

        $sql = 'INSERT INTO "projection_checkpoints" '
             . '("projection_name", "event_id", "tenant_id", "metadata", "applied_at") '
             . 'VALUES (:projection_name, :event_id, :tenant_id, :metadata, NOW()) '
             . 'ON CONFLICT ("projection_name", "event_id") DO NOTHING';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':projection_name', trim($projectionName));
        $stmt->bindValue(':event_id', trim($eventId));
        if ($tenantId !== null) {
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':tenant_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':metadata', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Guards the execution of a projection mutation, ensuring it executes once and only once.
     *
     * Execution Flow:
     * 1. Verify if the event has already been processed for this projection; if so, skip execution and return null.
     * 2. Execute the provided projection callback.
     * 3. Record the checkpoint in the database.
     * 4. Return the callback result.
     *
     * Logic behind the logic:
     * - Wrapping check and mark inside the projection workflow guarantees that duplicate queue
     *   retries are safely swallowed as no-ops without corrupting data.
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
        if ($this->isProcessed($projectionName, $eventId)) {
            return null;
        }

        $result = $action();
        $this->markProcessed($projectionName, $eventId, $tenantId, $metadata);

        return $result;
    }
}
