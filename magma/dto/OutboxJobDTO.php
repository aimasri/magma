<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: Outbox Job DTO
 *
 * Purpose:
 * - Represents an immutable job payload queued into the PostgreSQL transactional outbox table.
 *
 * Why / Why this design:
 * - `readonly class` guarantees that domain event listeners hand a strictly typed, tamper-proof
 *   payload across the transactional persistence boundary.
 *
 * Teaching notes:
 * - Keeping `tenantId` explicit on the DTO preserves multi-tenant context when outbox workers
 *   rehydrate and dispatch background jobs asynchronously.
 */
readonly class OutboxJobDTO
{
    /**
     * @param string $queue Logical queue channel name.
     * @param string $handlerClass Fully-qualified `JobInterface` handler class name.
     * @param array<string, mixed> $payload Serializable job arguments.
     * @param array<string, mixed> $headers Metadata headers (e.g. correlation IDs).
     * @param int|null $tenantId Owning tenant identifier, or null for global system jobs.
     */
    public function __construct(
        public string $queue,
        public string $handlerClass,
        public array $payload,
        public array $headers = [],
        public ?int $tenantId = null
    ) {}
}
