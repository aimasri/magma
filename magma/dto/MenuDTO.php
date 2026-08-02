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
    public int $id;
    public int $vendorId;
    public ?int $parentId;
    public string $name;
    public bool $isActive;

    public function __construct(int $id, int $vendorId, ?int $parentId, string $name, bool $isActive)
    {
        $this->id = $id;
        $this->vendorId = $vendorId;
        $this->parentId = $parentId;
        $this->name = $name;
        $this->isActive = $isActive;
    }
}
