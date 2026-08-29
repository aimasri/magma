<?php

namespace Magma\services;

use Magma\dto\MenuDTO;
use Magma\interfaces\cqrs\CommandBusInterface;
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
    private CommandBusInterface $commandBus;

    /**
     * Initializes the MenuPropagationService with the required command bus.
     *
     * Execution Flow:
     * 1. Injects the CommandBusInterface dependency.
     * 2. Assigns it to the class property for later dispatching of commands.
     *
     * Logic behind the logic:
     * - Dependency Injection (DI): Passing the command bus allows for loose coupling
     *   and easier unit testing by mocking the command bus.
     *
     * @param CommandBusInterface $commandBus
     */
    public function __construct(CommandBusInterface $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    /**
     * Propagates inactive status to all child menus recursively.
     *
     * Execution Flow:
     * 1. Create a new DeactivateChildMenusCommand with the target menu's ID and tenant ID.
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
        
        $command = new DeactivateChildMenusCommand($menu->id, $menu->tenantId);
        $this->commandBus->execute($command);
    }
}
