<?php

namespace Magma\domain\commands;

/**
 * Title: Deactivate Child Menus Command
 * 
 * Purpose:
 * - Represents a DTO command to recursively deactivate all child menus under a specific parent menu.
 * 
 * Why this design:
 * - Command Pattern (CQRS): Encapsulates all data required to perform a specific mutation into a single, immutable object.
 * - Immutability: Using `readonly` properties guarantees the command payload cannot be maliciously or accidentally altered after instantiation.
 * 
 * Teaching notes:
 * - This is just the payload; the actual execution logic resides in the corresponding Command Handler or Repository.
 */
class DeactivateChildMenusCommand
{
    public readonly int $parentId;
    public readonly int $tenantId;
    public readonly string $action;

    /**
     * Initializes the command payload with target parent and tenant details.
     * 
     * @param int $parentId The ID of the parent menu whose children should be deactivated.
     * @param int $tenantId The ID of the tenant owning the menus.
     */
    public function __construct(int $parentId, int $tenantId)
    {
        $this->parentId = $parentId;
        $this->tenantId = $tenantId;
        $this->action = 'deactivate_children';
    }
}
