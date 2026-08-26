<?php
declare(strict_types=1);

namespace Magma\dto;

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
