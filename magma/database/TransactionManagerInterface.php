<?php

namespace Magma\database;

/**
 * Transaction Manager Interface
 *
 * Purpose:
 * - Define a contract for executing closures within an atomic transaction block.
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
     * Executes a callback within a transaction boundary.
     *
     * Execution Flow:
     * 1. Begin the transaction.
     * 2. Execute the provided closure.
     * 3. If successful, commit the transaction and return the closure's result.
     * 4. If an exception is thrown, catch it, roll back the transaction, and re-throw the exception.
     *
     * Logic behind the logic:
     * - Using a closure-based approach (`callable`) rather than explicit `begin()` and `commit()` methods 
     *   forces the developer into a `try/catch` block, making it impossible to accidentally leave a 
     *   database transaction hanging open due to an unhandled exception.
     *
     * @param callable $callback The operations to run atomically.
     * @return mixed The result of the callback, if any.
     * @throws \Throwable If any operation inside the callback fails.
     */
    public function transactional(callable $callback): mixed;
}
