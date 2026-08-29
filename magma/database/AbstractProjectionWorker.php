<?php

declare(strict_types=1);

namespace Magma\database;

use Magma\queue\JobInterface;
use Magma\queue\IdempotentProjectionGuard;
use RuntimeException;
use Throwable;

/**
 * Title: Abstract CQRS Projection Worker
 *
 * Purpose:
 * - Base class for background workers responsible for updating CQRS read projections from domain events.
 * - Enforces transactional execution and idempotent guard checkpointing to prevent projection corruption.
 *
 * Why / Why this design:
 * - Template Method Pattern: Defines the invariant projection lifecycle (event extraction, idempotency check,
 *   database transaction boundary, error logging) while delegating specific read-model math to subclasses.
 * - Idempotency & Concurrency: Protects against duplicate queue delivery and racing asynchronous updates
 *   by coordinating with `IdempotentProjectionGuard`.
 *
 * Teaching notes:
 * - In a CQRS architecture, write-models append events or state changes, while read-models (projections)
 *   are asynchronously calculated aggregates optimized for fast querying. This worker standardizes that projection pipeline.
 */
abstract class AbstractProjectionWorker implements JobInterface
{
    protected IdempotentProjectionGuard $guard;
    protected DatabaseTransactionManager $transactionManager;

    /**
     * Initializes the projection worker with guarding and transactional capabilities.
     * 
     * @param IdempotentProjectionGuard $guard Guard instance ensuring idempotency of projection runs.
     * @param DatabaseTransactionManager $transactionManager Manager orchestrating database transactions.
     */
    public function __construct(
        IdempotentProjectionGuard $guard,
        DatabaseTransactionManager $transactionManager
    ) {
        $this->guard = $guard;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Executes the background projection lifecycle.
     *
     * Execution Flow:
     * 1. Extract the unique event/message ID and tenant ID from the payload.
     * 2. If no event ID is provided, generate a deterministic hash of the payload to serve as idempotency key.
     * 3. Invoke `beforeProject` lifecycle hook.
     * 4. Wrap the execution in a database transaction managed by `DatabaseTransactionManager`.
     * 5. Apply the projection within `IdempotentProjectionGuard::guard()`.
     * 6. Invoke `afterProject` lifecycle hook upon completion.
     *
     * Logic behind the logic:
     * - Performing both the projection calculation and the checkpoint insertion within the same transaction
     *   ensures that either the read model AND the checkpoint are committed together, or neither is.
     *
     * @param array<string, mixed> $payload The JSON-decoded payload from the queue.
     * @throws Throwable If transaction or projection fails.
     */
    public function handle(array $payload): void
    {
        $eventId = $this->resolveEventId($payload);
        $tenantId = isset($payload['tenant_id']) && is_scalar($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
        $projectionName = $this->getProjectionName();

        $this->beforeProject($payload);

        $this->transactionManager->transactional(function () use ($projectionName, $eventId, $tenantId, $payload): void {
            $this->guard->guard(
                $projectionName,
                $eventId,
                function () use ($payload): void {
                    $this->project($payload);
                },
                $tenantId,
                [
                    'handler' => static::class,
                    'projected_at' => date('c'),
                ]
            );
        });

        $this->afterProject($payload);
    }

    /**
     * Resolves the unique event identifier from the payload.
     *
     * @param array<string, mixed> $payload
     * @return string
     */
    protected function resolveEventId(array $payload): string
    {
        if (!empty($payload['event_id']) && is_scalar($payload['event_id'])) {
            return (string) $payload['event_id'];
        }

        if (!empty($payload['id']) && is_scalar($payload['id'])) {
            return (string) $payload['id'];
        }

        // Fallback: create deterministic SHA-256 hash of payload
        $encoded = json_encode($payload);
        if ($encoded === false) {
            throw new RuntimeException("Failed to encode payload for event hash generation.");
        }
        return hash('sha256', $encoded);
    }

    /**
     * Hook invoked before projection execution.
     *
     * @param array<string, mixed> $payload
     */
    protected function beforeProject(array $payload): void
    {
        // Optional override in child workers
    }

    /**
     * Hook invoked after successful projection commit.
     *
     * @param array<string, mixed> $payload
     */
    protected function afterProject(array $payload): void
    {
        // Optional override in child workers
    }

    /**
     * Returns the unique name of this projection (e.g. 'tenant_inventory_totals', 'order_metrics').
     *
     * @return string
     */
    abstract protected function getProjectionName(): string;

    /**
     * Performs the domain calculation and read-model persistence for this projection.
     *
     * @param array<string, mixed> $payload
     */
    abstract protected function project(array $payload): void;
}
