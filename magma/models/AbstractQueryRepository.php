<?php

declare(strict_types=1);

namespace Magma\models;

use PDO;
use PDOStatement;
use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Magma\dto\PaginationDTO;

/**
 * Title: Abstract Query Repository (CQRS Read Model Base)
 *
 * Purpose:
 * - Serve as the enterprise base repository for all CQRS read-model queries, reporting, and views.
 * - Enforce exclusive connection routing to the Read Replica PDO instance (`$dbRead`).
 * - Provide robust read-only query execution helpers, multi-tenant scoping, and keyset pagination.
 *
 * Why / Why this design:
 * - Implements Command Query Responsibility Segregation (CQRS) at the persistence boundary.
 * - Guarantees that read-only HTTP GET requests never acquire write connection handles to the primary database,
 *   optimizing connection pool sizing and enabling horizontal read scaling across read replicas.
 * - Prevents repository "God Classes" by segregating read models from mutating command models.
 *
 * Teaching notes:
 * - This repository should only execute SELECT queries.
 * - All state mutations (INSERT, UPDATE, DELETE) must extend `AbstractCommandRepository`.
 */
abstract class AbstractQueryRepository
{
    /**
     * Database connection coordinator.
     */
    protected DatabaseConnectionManager $dbManager;

    /**
     * Security multi-tenant context provider.
     */
    protected TenantContext $tenantContext;

    /**
     * Initializes the Abstract Query Repository.
     *
     * @param DatabaseConnectionManager $dbManager
     * @param TenantContext $tenantContext
     */
    public function __construct(DatabaseConnectionManager $dbManager, TenantContext $tenantContext)
    {
        $this->dbManager = $dbManager;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Retrieves the Read Replica PDO connection.
     *
     * @return PDO
     */
    protected function getDb(): PDO
    {
        return $this->dbManager->getReadConnection();
    }

    /**
     * Retrieves the active tenant/vendor ID from the security context, or null if unset.
     *
     * @return int|null
     */
    protected function getTenantId(): ?int
    {
        return $this->tenantContext->hasVendorId() ? $this->tenantContext->getVendorId() : null;
    }

    /**
     * Prepares and executes a read query, returning a single row or null.
     *
     * Execution Flow:
     * 1. Prepare the SQL query against the Read connection.
     * 2. Bind parameter values and execute the statement.
     * 3. Fetch associative row and return null if no record matched.
     *
     * @param string $sql SQL query string.
     * @param array<string|int, mixed> $params Parameter bindings.
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    /**
     * Prepares and executes a read query, returning an array of all matched rows.
     *
     * Execution Flow:
     * 1. Prepare and execute the SQL query on the Read replica.
     * 2. Fetch all associative rows into an array.
     *
     * @param string $sql SQL query string.
     * @param array<string|int, mixed> $params Parameter bindings.
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prepares and executes a query, returning a single scalar column value.
     *
     * Execution Flow:
     * 1. Prepare and execute the SQL query on the Read replica.
     * 2. Fetch the first column value or null if empty.
     *
     * @param string $sql SQL query string.
     * @param array<string|int, mixed> $params Parameter bindings.
     * @param int $columnIndex 0-indexed column offset.
     * @return mixed
     */
    protected function fetchColumn(string $sql, array $params = [], int $columnIndex = 0): mixed
    {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetchColumn($columnIndex);
        return $result !== false ? $result : null;
    }

    /**
     * Executes a keyset cursor pagination query efficiently without OFFSET.
     *
     * Execution Flow:
     * 1. Append cursor condition (`$cursorColumn > :last_id`) if $pagination->lastId is set.
     * 2. Fetch `$pagination->limit + 1` rows to check for subsequent pages without executing a separate COUNT(*).
     * 3. Determine if more items exist and compute the next cursor identifier.
     * 4. Return array containing items, nextCursor, and hasMore boolean.
     *
     * Logic behind the logic:
     * - Keyset pagination maintains constant O(1) query time against indexed columns regardless of dataset depth, 
     *   avoiding the catastrophic O(N) performance degradation of SQL OFFSET clauses.
     *
     * @param string $baseSql Base SELECT query without ORDER BY or LIMIT.
     * @param array<string, mixed> $params Query parameter bindings.
     * @param PaginationDTO $pagination Keyset pagination parameters.
     * @param string $cursorColumn Column to paginate on (default 'id').
     * @param string $direction Sort direction ('ASC' or 'DESC').
     * @return array{items: array, nextCursor: int|null, hasMore: bool, limit: int}
     */
    protected function cursorPaginate(
        string $baseSql,
        array $params,
        PaginationDTO $pagination,
        string $cursorColumn = 'id',
        string $direction = 'ASC'
    ): array {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $operator = $direction === 'ASC' ? '>' : '<';
        
        $sql = $baseSql;
        if ($pagination->lastId !== null) {
            $hasWhere = stripos($sql, 'WHERE') !== false;
            $clause = ($hasWhere ? ' AND ' : ' WHERE ') . "\"{$cursorColumn}\" {$operator} :cursor_last_id";
            $sql .= $clause;
            $params[':cursor_last_id'] = $pagination->lastId;
        }

        $limitPlusOne = $pagination->limit + 1;
        $sql .= " ORDER BY \"{$cursorColumn}\" {$direction} LIMIT {$limitPlusOne}";

        $rows = $this->fetchAll($sql, $params);
        $hasMore = count($rows) > $pagination->limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if (!empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = isset($lastItem[$cursorColumn]) ? (int) $lastItem[$cursorColumn] : null;
        }

        return [
            'items' => $rows,
            'nextCursor' => $hasMore ? $nextCursor : null,
            'hasMore' => $hasMore,
            'limit' => $pagination->limit,
        ];
    }

    /**
     * Helper to prepare and execute parameterized statements.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return PDOStatement
     */
    private function executeQuery(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
