<?php

declare(strict_types=1);

namespace Magma\database;

use Throwable;

/**
 * Title: Transaction Manager Interface
 *
 * Purpose:
 * - Define a contract for executing closures within an atomic transaction or savepoint boundary.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle: Services should not interact directly with a `PDO` instance 
 *   to begin or commit transactions. By depending on this abstraction, the domain layer remains 
 *   completely agnostic of the underlying SQL storage mechanism.
 *
 * Teaching notes:
 * - Encapsulating transaction boundaries makes unit testing business logic significantly 
 *   easier, as the manager can be mocked to simply execute the callback immediately 
 *   without requiring a real database connection.
 */
interface TransactionManagerInterface
{
    /**
     * Executes a callback within an atomic transaction boundary.
     *
     * Execution Flow:
     * 1. Begin the transaction or create a nested savepoint.
     * 2. Execute the provided closure.
     * 3. If successful, commit or release savepoint, and return result.
     * 4. If a Throwable is thrown, catch it, roll back transaction/savepoint, and re-throw.
     *
     * Logic behind the logic:
     * - Using a closure-based approach (`callable`) rather than manual begin/commit pairs 
     *   guarantees that database transactions are never left uncommitted or unhandled.
     *
     * @param callable $callback The operations to run atomically.
     * @return mixed The result of the callback.
     * @throws Throwable If any operation inside the callback fails.
     */
    public function transactional(callable $callback): mixed;
}
