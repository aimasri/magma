<?php

declare(strict_types=1);

namespace Magma\models;

use PDO;
use PDOStatement;
use RuntimeException;
use Magma\database\DatabaseConnectionManager;
use Magma\database\PostgreSqlInsertBuilder;
use Magma\security\TenantContext;

/**
 * Title: Abstract Command Repository (CQRS Write Model Base)
 *
 * Purpose:
 * - Serve as the enterprise base repository for all CQRS write-model persistence operations.
 * - Enforce exclusive connection routing to the Primary Write Master PDO instance (`$dbWrite`).
 * - Provide atomic PostgreSQL `insertAndGetId()` helpers, safe mutating routines (update/delete), 
 *   and multi-tenant security scoping.
 *
 * Why / Why this design:
 * - Implements Command Query Responsibility Segregation (CQRS) at the database layer.
 * - Ensures mutating operations (INSERT, UPDATE, DELETE) always hit the primary master node, 
 *   preventing replication lag anomalies and read-only node execution errors.
 * - Centralizes atomic primary key resolution using PostgreSQL's native `RETURNING id` syntax.
 *
 * Teaching notes:
 * - All queries issued by classes inheriting this base should be state-mutating operations.
 * - Read-only queries should extend `AbstractQueryRepository`.
 */
abstract class AbstractCommandRepository
{
    /**
     * Database connection coordinator.
     */
    protected DatabaseConnectionManager $dbManager;

    /**
     * Security multi-tenant context provider.
     */
    protected ?TenantContext $tenantContext = null;

    /**
     * Initializes the Abstract Command Repository.
     *
     * @param DatabaseConnectionManager $dbManager
     * @param TenantContext|null $tenantContext
     */
    public function __construct(DatabaseConnectionManager $dbManager, ?TenantContext $tenantContext = null)
    {
        $this->dbManager = $dbManager;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Retrieves the Master Write PDO connection.
     *
     * @return PDO
     */
    protected function getDb(): PDO
    {
        return $this->dbManager->getWriteConnection();
    }

    /**
     * Retrieves the active tenant ID from the security context, or null if unset.
     *
     * @return int|null
     */
    protected function getTenantId(): ?int
    {
        return ($this->tenantContext !== null && $this->tenantContext->hasTenantId()) ? $this->tenantContext->getTenantId() : null;
    }

    /**
     * Atomically inserts a row into a table and resolves its generated primary key ID via PostgreSQL `RETURNING`.
     *
     * Execution Flow:
     * 1. Delegate SQL generation and execution to PostgreSqlInsertBuilder on the Write PDO connection.
     * 2. Extract and cast the returned primary key column to integer.
     * 3. Return the generated ID.
     *
     * Logic behind the logic:
     * - Using PostgreSQL `RETURNING id` is driver-safe and sequence-agnostic, avoiding `PDO::lastInsertId()` 
     *   null/zero return issues in PostgreSQL.
     *
     * @param string $table Target table name.
     * @param array<string, mixed> $data Associative column => value array.
     * @param string $primaryKey Primary key column name (default 'id').
     * @return int The newly created row ID.
     */
    protected function insertAndGetId(string $table, array $data, string $primaryKey = 'id'): int
    {
        return PostgreSqlInsertBuilder::insertAndGetId($this->getDb(), $table, $data, $primaryKey);
    }

    /**
     * Executes an UPDATE statement on a table with parameterized values.
     *
     * Execution Flow:
     * 1. Construct parameterized SET clauses quoting column names.
     * 2. Append WHERE conditions and bind both data and condition parameters.
     * 3. Execute statement on Write PDO connection and return affected row count.
     *
     * @param string $table Target table name.
     * @param array<string, mixed> $data Data columns to update.
     * @param string $where WHERE condition clause (e.g. '"id" = :id AND "tenant_id" = :tenant_id').
     * @param array<string, mixed> $whereParams Parameter bindings for the WHERE clause.
     * @return int Number of affected rows.
     * @throws RuntimeException If $data is empty.
     */
    protected function executeUpdate(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            throw new RuntimeException("Update operation on table [{$table}] requires at least one column.");
        }

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $paramKey = ':set_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $column);
            $setParts[] = "\"{$column}\" = {$paramKey}";
            $params[$paramKey] = $value;
        }

        $setSql = implode(', ', $setParts);
        $sql = "UPDATE \"{$table}\" SET {$setSql} WHERE {$where}";

        $mergedParams = array_merge($params, $whereParams);
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($mergedParams);

        return $stmt->rowCount();
    }

    /**
     * Executes a DELETE statement on a table with parameterized WHERE conditions.
     *
     * Execution Flow:
     * 1. Construct DELETE query with standard quoted table name.
     * 2. Prepare statement and execute with provided condition parameters.
     * 3. Return number of affected rows.
     *
     * @param string $table Target table name.
     * @param string $where WHERE condition clause.
     * @param array<string, mixed> $whereParams Parameter bindings.
     * @return int Number of deleted rows.
     */
    protected function executeDelete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM \"{$table}\" WHERE {$where}";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($whereParams);

        return $stmt->rowCount();
    }

    /**
     * Prepares and executes an arbitrary SQL mutation on the Write connection.
     *
     * @param string $sql SQL query string.
     * @param array<string|int, mixed> $params Parameter bindings.
     * @return int Number of affected rows.
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
