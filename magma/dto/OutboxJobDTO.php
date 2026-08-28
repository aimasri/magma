<?php
declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: Outbox Job DTO
 *
 * Purpose:
 * - Represents a standardized job payload intended for the transactional outbox table.
 *
 * Why this design:
 * - Uses immutable DTOs to enforce strict data boundaries between the event listeners and the database outbox layer, ensuring the payload is serializable and structure-checked before insertion.
 *
 * Teaching notes:
 * - Keeping DTOs read-only guarantees they aren't mutated mid-flight by middlewares or queue dispatchers.
 */
class OutboxJobDTO
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly string $queue,
        public readonly string $handlerClass,
        public readonly array $payload,
        public readonly array $headers = []
    ) {}
}
