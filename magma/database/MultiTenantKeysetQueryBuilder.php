<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use InvalidArgumentException;
use Magma\dto\PaginationDTO;

/**
 * Title: Multi-Tenant Keyset Query Builder
 *
 * Purpose:
 * - Build and execute high-performance cursor-based SQL queries with automatic multi-tenant and multi-venue scoping.
 * - Replace SQL `OFFSET / LIMIT` pagination with constant-time B-Tree indexed keyset pagination.
 *
 * Why / Why this design:
 * - Guarantees data isolation in multi-tenant SaaS applications by enforcing tenant and venue boundaries at the builder level.
 * - Keyset (cursor) seeking avoids loading entire table collections into memory and guarantees stable pagination 
 *   even when new records are concurrently inserted.
 * - Fetches `limit + 1` rows to determine `hasMore` and resolve `nextCursor` without executing expensive secondary `COUNT(*)` queries.
 *
 * Teaching notes:
 * - For forward pagination (`ASC`), the cursor condition is `"id" > :cursor_last_id`.
 * - For backward pagination (`DESC`), the cursor condition is `"id" < :cursor_last_id`.
 */
class MultiTenantKeysetQueryBuilder
{
    public const DEFAULT_LIMIT = 50;

    private string $table = '';
    private string $alias = '';
    /** @var array<int, string> */
    private array $columns = ['*'];
    private ?int $tenantId = null;
    private string $tenantColumn = 'tenant_id';
    private ?int $venueId = null;
    private string $venueColumn = 'venue_id';
    /** @var array<int, string> */
    private array $whereClauses = [];
    /** @var array<string, mixed> */
    private array $params = [];
    private ?PaginationDTO $pagination = null;
    private string $cursorColumn = 'id';
    private string $direction = 'ASC';

    /**
     * Sets the primary target database table and optional alias.
     *
     * @param string $table Database table name.
     * @param string $alias Optional table alias prefix.
     * @return self
     */
    public function from(string $table, string $alias = ''): self
    {
        $this->table = $table;
        $this->alias = $alias;
        return $this;
    }

    /**
     * Sets the columns to select.
     *
     * @param array<int, string> $columns
     * @return self
     */
    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Sets the mandatory tenant ID scope.
     *
     * @param int $tenantId
     * @param string $tenantColumn
     * @return self
     */
    public function withTenant(int $tenantId, string $tenantColumn = 'tenant_id'): self
    {
        $this->tenantId = $tenantId;
        $this->tenantColumn = $tenantColumn;
        return $this;
    }

    /**
     * Sets an optional venue ID scope for multi-venue tenants.
     *
     * @param int|null $venueId
     * @param string $venueColumn
     * @return self
     */
    public function withVenue(?int $venueId, string $venueColumn = 'venue_id'): self
    {
        $this->venueId = $venueId;
        $this->venueColumn = $venueColumn;
        return $this;
    }

    /**
     * Adds an arbitrary WHERE condition clause with parameter bindings.
     *
     * @param string $clause SQL condition string.
     * @param array<string, mixed> $params Parameter bindings for the clause.
     * @return self
     */
    public function where(string $clause, array $params = []): self
    {
        $this->whereClauses[] = $clause;
        foreach ($params as $key => $val) {
            $this->params[$key] = $val;
        }
        return $this;
    }

    /**
     * Configures keyset pagination parameters.
     *
     * @param PaginationDTO $pagination
     * @param string $cursorColumn Column to paginate on (default 'id').
     * @param string $direction Sort direction ('ASC' or 'DESC').
     * @return self
     */
    public function paginate(PaginationDTO $pagination, string $cursorColumn = 'id', string $direction = 'ASC'): self
    {
        $this->pagination = $pagination;
        $this->cursorColumn = $cursorColumn;
        $this->direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return $this;
    }

    /**
     * Assembles the parameterized SQL statement and parameter bindings.
     *
     * Execution Flow:
     * 1. Format table reference and column selections with proper quoting.
     * 2. Build WHERE clauses including tenant scoping, venue scoping, and user conditions.
     * 3. Append keyset cursor boundary condition if $pagination->lastId is set.
     * 4. Append ORDER BY and LIMIT ($limit + 1).
     * 5. Return associative array of SQL and parameter map.
     *
     * @return array{sql: string, params: array<string, mixed>}
     * @throws InvalidArgumentException If table is not set.
     */
    public function build(): array
    {
        if (empty($this->table)) {
            throw new InvalidArgumentException("Cannot build query: Target table is not defined.");
        }

        $params = $this->params;
        $tableRef = "\"{$this->table}\"";
        $prefix = '';
        if (!empty($this->alias)) {
            $tableRef .= " \"{$this->alias}\"";
            $prefix = "\"{$this->alias}\".";
        }

        // Format column selections
        $formattedCols = [];
        if (empty($this->columns) || (count($this->columns) === 1 && $this->columns[0] === '*')) {
            $formattedCols[] = $prefix ? "{$prefix}*" : "*";
        } else {
            foreach ($this->columns as $col) {
                $cleanCol = (string) $col;
                $formattedCols[] = str_contains($cleanCol, '.') || str_contains($cleanCol, '(') 
                    ? $cleanCol 
                    : "{$prefix}\"{$cleanCol}\"";
            }
        }
        $selectClause = implode(', ', $formattedCols);

        $conditions = $this->whereClauses;

        // Apply tenant scoping
        if ($this->tenantId !== null) {
            $paramTenant = ':builder_tenant_id';
            $conditions[] = "{$prefix}\"{$this->tenantColumn}\" = {$paramTenant}";
            $params[$paramTenant] = $this->tenantId;
        }

        // Apply venue scoping
        if ($this->venueId !== null) {
            $paramVenue = ':builder_venue_id';
            $conditions[] = "{$prefix}\"{$this->venueColumn}\" = {$paramVenue}";
            $params[$paramVenue] = $this->venueId;
        }

        $limit = self::DEFAULT_LIMIT;
        if ($this->pagination !== null) {
            $limit = $this->pagination->limit;
            if ($this->pagination->lastId !== null) {
                $operator = $this->direction === 'ASC' ? '>' : '<';
                $paramCursor = ':builder_last_cursor_id';
                $conditions[] = "{$prefix}\"{$this->cursorColumn}\" {$operator} {$paramCursor}";
                $params[$paramCursor] = $this->pagination->lastId;
            }
        }

        $whereSql = !empty($conditions) ? " WHERE " . implode(' AND ', $conditions) : '';
        $orderCol = "{$prefix}\"{$this->cursorColumn}\"";
        $limitPlusOne = $limit + 1;

        $sql = "SELECT {$selectClause} FROM {$tableRef}{$whereSql} ORDER BY {$orderCol} {$this->direction} LIMIT {$limitPlusOne}";

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * Executes the keyset query against the given PDO connection and returns formatted pagination output.
     *
     * Execution Flow:
     * 1. Build the SQL and parameter bindings via build().
     * 2. Prepare and execute the PDO statement.
     * 3. Fetch all matching rows.
     * 4. Check if rows returned exceed the limit:
     *    a. If yes, pop the extra row and set hasMore = true.
     *    b. Resolve nextCursor identifier from the last remaining row.
     * 5. Return structured pagination payload.
     *
     * @param PDO $pdo Active PDO connection (Read Replica).
     * @return array{items: array<int, array<string, mixed>>, nextCursor: int|null, hasMore: bool, limit: int}
     */
    public function execute(PDO $pdo): array
    {
        $built = $this->build();
        $stmt = $pdo->prepare($built['sql']);
        $stmt->execute($built['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $limit = $this->pagination !== null ? $this->pagination->limit : self::DEFAULT_LIMIT;
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if (!empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = isset($lastItem[$this->cursorColumn]) ? (int) $lastItem[$this->cursorColumn] : null;
        }

        return [
            'items' => $rows,
            'nextCursor' => $hasMore ? $nextCursor : null,
            'hasMore' => $hasMore,
            'limit' => $limit,
        ];
    }
}
