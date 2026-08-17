<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

/**
 * Title: CQRS Command Bus Interface
 *
 * Purpose:
 * - Define the contract for dispatching and executing write commands within a CQRS architecture.
 * - Decouple command producers (HTTP controllers, event listeners, CLI daemons) from command handlers.
 *
 * Why / Why this design:
 * - Implements the Command Pattern and Command Query Responsibility Segregation (CQRS).
 * - Enforces a single entry point for state mutations, enabling cross-cutting concerns 
 *   (database transaction boundaries, authorization guards, domain event dispatching, audit logging) 
 *   to be decorated or intercepted without polluting domain models.
 *
 * Teaching notes:
 * - A Command represents an intent to change state (e.g. CreateUserCommand, UpdatePriceCommand).
 * - Handlers contain the execution orchestration, interacting with repositories and domain models.
 */
interface CommandBusInterface
{
    /**
     * Executes a command object by routing it to its registered command handler.
     *
     * Execution Flow:
     * 1. Resolves the handler associated with the command class name.
     * 2. Executes the handler within transaction and security boundaries.
     * 3. Returns the resulting output (e.g., generated ID, domain entity, or void).
     *
     * Logic behind the logic:
     * - Accepting `object $command` preserves strong typing and immutability for command DTOs.
     *
     * @param object $command The command object encapsulating operation parameters.
     * @return mixed Result of command execution.
     */
    public function execute(object $command): mixed;
}
