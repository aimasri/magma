<?php

namespace Magma\services;

use Magma\dto\MenuDTO;
use Magma\interfaces\cqrs\CommandInterface;
use Magma\domain\commands\DeactivateChildMenusCommand;

/**
 * Title: Menu Propagation Service
 *
 * Purpose:
 * - Handles complex domain logic associated with Menus (e.g., recursive invalidation/propagation)
 *   that should not live in the pure Data Transfer Objects (DTOs) or the basic repositories.
 * - Coordinates state changes across bounded entities.
 *
 * Why / Why this design:
 * - Keeps models as anemic data structures (DTOs).
 * - Centralizes business rules for state transitions in a Domain Service.
 * - CQRS integration: Uses command dispatching to perform side effects rather than direct repository updates.
 *
 * Teaching notes:
 * - Shows how to model cross-entity operations when DDD (Domain-Driven Design) aggregates are not fully strictly applied.
 * - Compare to Event Sourcing where this might be handled by an event listener reacting to a "MenuDeactivated" event.
 */
class MenuPropagationService
{
    private CommandInterface $menuCommandRepo;

    public function __construct(CommandInterface $menuCommandRepo)
    {
        $this->menuCommandRepo = $menuCommandRepo;
    }

    /**
     * Propagates inactive status to all child menus recursively.
     *
     * Execution Flow:
     * 1. Create a new DeactivateChildMenusCommand with the target menu's ID and vendor ID.
     * 2. Dispatch the command via the command repository to execute the state change.
     *
     * Logic behind the logic:
     * - CQRS Pattern: Encapsulates the update logic inside a specific Command, decoupling the intent from the underlying SQL or ORM implementation.
     *
     * @param MenuDTO $menu
     * @return void
     */
    public function cascadeDeactivation(MenuDTO $menu): void
    {
        // Example domain logic: if a parent menu is deactivated, 
        // all its children must be deactivated.
        // The implementation details would involve querying children 
        // and dispatching update commands.
        
        $command = new DeactivateChildMenusCommand($menu->id, $menu->vendorId);
        $this->menuCommandRepo->execute($command);
    }
}
