<?php

declare(strict_types=1);

namespace Magma\models;

use PDO;
use Magma\database\MultiTenantKeysetQueryBuilder;
use Magma\dto\PaginationDTO;

/**
 * Title: Abstract Keyset Repository (High-Performance Read Pagination Base)
 *
 * Purpose:
 * - Serve as the specialized base repository for all read models requiring high-volume keyset pagination.
 * - Eliminate performance penalties associated with traditional SQL `OFFSET` pagination on large tables.
 * - Enforce automatic multi-tenant scoping (`tenant_id`) and protocol-agnostic `PaginationDTO` integration.
 *
 * Why / Why this design:
 * - Traditional `OFFSET / LIMIT` pagination forces the PostgreSQL database engine to scan and discard 
 *   thousands of rows, resulting in linear $O(N)$ CPU and I/O degradation. Keyset (cursor-based) pagination 
 *   operates in constant $O(1)$ time by seeking directly via indexed B-Tree columns (`WHERE id > :cursor`).
 * - Standardizing `PaginationDTO` decouples the repository from HTTP request superglobals (`$_GET`), 
 *   allowing pagination logic to run seamlessly across CLI commands, GraphQL APIs, and background jobs.
 *
 * Teaching notes:
 * - Always ensure the column used as the cursor (e.g. `id`, `created_at`) is indexed.
 * - The query fetches `limit + 1` rows to determine if subsequent pages exist without executing a separate `COUNT(*)`.
 */
abstract class AbstractKeysetRepository extends AbstractQueryRepository
{
    /**
     * Executes a standardized keyset-paginated query with automatic multi-tenant scoping.
     *
     * Execution Flow:
     * 1. Initialize MultiTenantKeysetQueryBuilder with table, columns, and alias.
     * 2. Apply multi-tenant scoping from the injected TenantContext if tenant ID exists.
     * 3. Apply custom where clauses and parameter bindings passed in $conditions.
     * 4. Configure cursor pagination parameters from the PaginationDTO.
     * 5. Execute query on the Read replica PDO connection.
     * 6. Extract rows, compute next cursor ID from the boundary element, and return formatted payload.
     *
     * Logic behind the logic:
     * - Decouples SQL generation from domain queries while guaranteeing strict tenant isolation.
     *
     * @param string $table Target database table.
     * @param PaginationDTO $pagination Keyset pagination parameters (limit, lastId).
     * @param array<int|string, mixed> $conditions Associative column => value or SQL clauses to filter by.
     * @param string $cursorColumn Column to paginate on (default 'id').
     * @param string $direction Sort direction ('ASC' or 'DESC').
     * @param array<int, string> $columns List of columns to select.
     * @param string|null $tableAlias Optional table alias prefix (e.g., 'm').
     * @param string $tenantColumn Database column representing tenant ownership (default 'tenant_id').
     * @return array{items: array<int, array<string, mixed>>, nextCursor: int|null, hasMore: bool, limit: int}
     */
    protected function paginateKeyset(
        string $table,
        PaginationDTO $pagination,
        array $conditions = [],
        string $cursorColumn = 'id',
        string $direction = 'ASC',
        array $columns = ['*'],
        ?string $tableAlias = null,
        string $tenantColumn = 'tenant_id'
    ): array {
        $builder = new MultiTenantKeysetQueryBuilder();
        $builder->from($table, $tableAlias ?? '')
                ->select($columns);

        $tenantId = $this->getTenantId();
        if ($tenantId !== null) {
            $builder->withTenant($tenantId, $tenantColumn);
        }

        foreach ($conditions as $clause => $paramValue) {
            if (is_int($clause)) {
                // Raw SQL clause with no parameters
                $builder->where(is_scalar($paramValue) ? (string) $paramValue : '');
            } elseif (!str_contains((string)$clause, ' ')) {
                // Simple 'column' => value equality condition
                $paramKey = ':cond_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $clause);
                $colRef = ($tableAlias ? "\"{$tableAlias}\"." : '') . "\"{$clause}\"";
                $builder->where("{$colRef} = {$paramKey}", [$paramKey => $paramValue]);
            } else {
                // Complex 'column > ?' or 'name LIKE :name' with parameters
                $params = is_array($paramValue) ? $paramValue : [':param_' . uniqid() => $paramValue];
                $builder->where((string) $clause, $params);
            }
        }

        $builder->paginate($pagination, $cursorColumn, $direction);

        return $builder->execute($this->getDb());
    }
}
