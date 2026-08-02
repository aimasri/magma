<?php

namespace Magma\interfaces\cqrs;

/**
 * Title: Command Interface (CQRS Write Model)
 *
 * Purpose:
 * - Represents the write model in a CQRS architecture.
 *
 * Why this design:
 * - CQRS Pattern: Strictly separates state-modifying operations (commands) from read-only queries.
 * - SOLID Principles: Prevents repository "God Classes" by giving each command a Single Responsibility.
 *
 * Teaching notes:
 * - Commands should only execute side effects (INSERT, UPDATE, DELETE).
 * - Avoid returning complex domain models from commands; returning an ID, boolean, or void is standard.
 */
interface CommandInterface
{
    /**
     * Executes the command.
     *
     * @param array|object $payload Data required to perform the write operation.
     * @return mixed Result of the command (e.g., ID of created resource, boolean status).
     */
    public function execute(mixed $payload): mixed;
}
