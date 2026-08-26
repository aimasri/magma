<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Title: PostgreSQL Savepoint Transaction Manager
 *
 * Purpose:
 * - Implement `TransactionManagerInterface` with robust nested savepoint support.
 * - Manage explicit boundaries of `BEGIN`, `COMMIT`, `ROLLBACK`, `SAVEPOINT`, `RELEASE SAVEPOINT`, 
 *   and `ROLLBACK TO SAVEPOINT`.
 *
 * Why / Why this design:
 * - In PostgreSQL, any SQL error or unhandled exception within an active transaction transitions the connection 
 *   into an aborted transaction state (`ERROR: current transaction is aborted, commands ignored until end of transaction block`).
 * - Savepoints allow nested service workflows to fail and recover without aborting or corrupting the master PDO transaction.
 * - Centralizes database transaction boundaries, adhering strictly to the Single Responsibility Principle (SRP).
 *
 * Teaching notes:
 * - Top-level transactions open a physical PDO transaction (`BEGIN`). Nested calls create SQL savepoints (`SAVEPOINT trans_N`).
 * - Catching `Throwable` guarantees that fatal PHP `Error` and `TypeError` exceptions roll back database mutations safely.
 */
class DatabaseTransactionManager implements TransactionManagerInterface
{
    /**
     * Database connection manager.
     */
    private DatabaseConnectionManager $dbManager;

    /**
     * Current transaction nesting level counter.
     */
    private int $transactionLevel = 0;

    /**
     * Initializes the Transaction Manager.
     *
     * @param DatabaseConnectionManager $dbManager
     */
    public function __construct(DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Executes a callback within a managed transaction or savepoint block.
     *
     * Execution Flow:
     * 1. Inspect current transaction level.
     * 2. If level is 0, start a physical transaction (`beginTransaction()`) and set level to 1.
     * 3. If level >= 1, issue a `SAVEPOINT trans_{level}` statement and increment level.
     * 4. Execute the callback.
     * 5. If successful:
     *    a. If level > 1, decrement level and issue `RELEASE SAVEPOINT trans_{level}`.
     *    b. If level == 1, decrement level to 0 and issue `commit()`.
     *    c. Return callback result.
     * 6. If any Throwable is caught:
     *    a. If level > 1, decrement level and issue `ROLLBACK TO SAVEPOINT trans_{level}`.
     *    b. If level == 1, decrement level to 0 and issue `rollBack()`.
     *    c. Re-throw the original Throwable.
     *
     * Logic behind the logic:
     * - Nested transactions inherit the outer boundary while maintaining isolated recovery points.
     *
     * @param callable $callback The business operation to execute.
     * @return mixed The result returned by the callback.
     * @throws Throwable
     */
    public function transactional(callable $callback): mixed
    {
        try {
            $this->begin();
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                $this->rollBack();
            } catch (Throwable $rollbackError) {
                error_log("Rollback failed: " . $rollbackError->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Manually begins a transaction or savepoint.
     *
     * @return void
     */
    public function begin(): void
    {
        $dbWrite = $this->dbManager->getWriteConnection();

        if ($this->transactionLevel === 0) {
            $dbWrite->beginTransaction();
            $dbWrite->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $this->transactionLevel = 1;
            $this->dbManager->forceWriteForReads(true);
        } else {
            $savepoint = 'trans_' . $this->transactionLevel;
            $dbWrite->exec("SAVEPOINT {$savepoint}");
            $this->transactionLevel++;
        }
    }

    /**
     * Manually commits the current transaction or releases the savepoint.
     *
     * @return void
     */
    public function commit(): void
    {
        $dbWrite = $this->dbManager->getWriteConnection();

        if ($this->transactionLevel === 0) {
            throw new RuntimeException("Cannot commit: No active transaction.");
        }

        if ($this->transactionLevel > 1) {
            $this->transactionLevel--;
            $savepoint = 'trans_' . $this->transactionLevel;
            $dbWrite->exec("RELEASE SAVEPOINT {$savepoint}");
        } else {
            $this->transactionLevel = 0;
            try {
                if ($dbWrite->inTransaction()) {
                    $dbWrite->commit();
                }
            } finally {
                $this->dbManager->forceWriteForReads(false);
            }
        }
    }

    /**
     * Manually rolls back the current transaction or rolls back to the savepoint.
     *
     * @return void
     */
    public function rollBack(): void
    {
        $dbWrite = $this->dbManager->getWriteConnection();

        if ($this->transactionLevel === 0) {
            throw new RuntimeException("Cannot roll back: No active transaction.");
        }

        if ($this->transactionLevel > 1) {
            $this->transactionLevel--;
            $savepoint = 'trans_' . $this->transactionLevel;
            $dbWrite->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        } else {
            $this->transactionLevel = 0;
            try {
                if ($dbWrite->inTransaction()) {
                    $dbWrite->rollBack();
                }
            } finally {
                $this->dbManager->forceWriteForReads(false);
            }
        }
    }

    /**
     * Returns the current transaction nesting level (0 = none, 1 = root transaction, >1 = nested savepoints).
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Determines whether a transaction or savepoint is currently active.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }
}
