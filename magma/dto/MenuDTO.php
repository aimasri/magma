<?php

namespace Magma\dto;

/**
 * Title: Menu Data Transfer Object
 * Purpose:
 * - Represents a structurally guaranteed, strictly typed Menu item.
 * - Handles data transport between the persistence layer and the application/presentation layers.
 * Why/Why this design:
 * - DTOs ensure that business logic and propagation rules do not leak into the data structure.
 * - Provides strict typing without the overhead of heavy Active Record models or \ArrayAccess.
 * Teaching notes:
 * - DTOs are a staple in Enterprise architecture for establishing a rigid contract of what data looks like when moving across architectural boundaries.
 */
class MenuDTO
{
    /**
     * Constructs an immutable Menu Data Transfer Object.
     *
     * Logic behind the logic:
     * - Employs PHP 8+ constructor property promotion with readonly modifiers to ensure the DTO is deeply immutable once instantiated.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly ?int $parentId,
        public readonly string $name,
        public readonly bool $isActive
    ) {}
}
