<?php

namespace Magma\database;

use PDO;
use Throwable;

/**
 * Database Transaction Manager
 *
 * Purpose:
 * - Implement the `TransactionManagerInterface` using a raw PDO connection.
 * - Manage the explicit boundaries of `BEGIN`, `COMMIT`, and `ROLLBACK`.
 *
 * Why / Why this design:
 * - Centralizes the PDO transaction API. If we ever needed to implement savepoints 
 *   for nested transactions, we only have to modify this single class rather than 
 *   hunting down raw `$pdo->beginTransaction()` calls scattered across the codebase.
 *
 * Teaching notes:
 * - The transaction manager safely supports nested calls by checking `inTransaction()`. 
 *   This ensures that inner service calls inherit the outermost transaction boundary 
 *   without throwing PDO exceptions or committing prematurely.
 */
class DatabaseTransactionManager implements TransactionManagerInterface
{
    private PDO $dbWrite;

    public function __construct(PDO $dbWrite)
    {
        $this->dbWrite = $dbWrite;
    }

    /**
     * Executes a callback within a PDO transaction block.
     *
     * Execution Flow:
     * 1. Trigger `beginTransaction()` on the Write connection.
     * 2. Execute the `$callback`.
     * 3. Trigger `commit()` if execution reaches the end without exceptions.
     * 4. Catch any `Throwable` (Exceptions or Errors), trigger `rollBack()`, and re-throw.
     *
     * Logic behind the logic:
     * - By catching `Throwable`, we guarantee that even fatal PHP TypeErrors or 
     *   Engine Errors will successfully roll back the database state, preventing 
     *   partial inserts or corruption.
     *
     * @param callable $callback
     * @return mixed
     * @throws Throwable
     */
    public function transactional(callable $callback): mixed
    {
        $isNested = $this->dbWrite->inTransaction();

        if (!$isNested) {
            $this->dbWrite->beginTransaction();
        }

        try {
            $result = $callback();
            
            if (!$isNested) {
                $this->dbWrite->commit();
            }
            
            return $result;
        } catch (Throwable $e) {
            if (!$isNested) {
                $this->dbWrite->rollBack();
            }
            throw $e;
        }
    }
}
