<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use InvalidArgumentException;

/**
 * Title: CQRS Eager Relation Batch Loader
 *
 * Purpose:
 * - Resolve and attach 1-to-many and many-to-many child relationships across collections of parent entities in memory.
 * - Eliminate $O(N)$ N+1 query storms in catalog controllers, API feeds, and admin dashboards without heavy ORM overhead.
 *
 * Why / Why this design:
 * - Active Record ORMs often introduce massive memory bloat and opaque lazy-loading bugs. 
 * - By executing a single parameterized `WHERE parent_id IN (:p0, :p1, ...)` query and grouping child rows 
 *   into parent entity models in linear $O(M)$ time in PHP memory, we maintain ultra-low latency and minimal memory usage.
 *
 * Teaching notes:
 * - Parent collections are passed by reference (`array &$parents`), allowing in-place relationship hydration.
 * - Both array-based records and object-based domain entities are supported automatically.
 */
class EagerRelationBatchLoader
{
    /**
     * Eagerly loads a One-to-Many relationship across an array of parent records.
     *
     * Execution Flow:
     * 1. If $parents array is empty, return immediately.
     * 2. Extract all distinct parent IDs from the collection.
     * 3. Construct parameterized placeholders for parent IDs.
     * 4. Build and execute the child query on the Read replica with optional tenant scoping.
     * 5. Group retrieved child records into a lookup dictionary keyed by the foreign key.
     * 6. Iterate through parents by reference, attaching their matched children array (or empty array if none).
     * 7. Return the hydrated parents collection.
     *
     * @param PDO $pdo Active PDO connection (Read Replica).
     * @param array<int, mixed> &$parents Array of parent records (arrays or objects), modified in place.
     * @param string $childTable Target child database table.
     * @param string $foreignKey Foreign key column on child table referencing the parent.
     * @param string $relationKey Property or array key to assign the children array to.
     * @param array<int, string> $columns List of columns to select from child table.
     * @param int|null $tenantId Optional tenant ID for multi-tenant isolation.
     * @param string $parentPrimaryKey Primary key on parent entity (default 'id').
     * @param string $tenantColumn Tenant column on child table (default 'tenant_id').
     * @param callable|null $transformCallback Optional transformer callback for child rows.
     * @return array<int, mixed> The hydrated parents array.
     */
    public static function loadOneToMany(
        PDO $pdo,
        array &$parents,
        string $childTable,
        string $foreignKey,
        string $relationKey,
        array $columns = ['*'],
        ?int $tenantId = null,
        string $parentPrimaryKey = 'id',
        string $tenantColumn = 'tenant_id',
        ?callable $transformCallback = null
    ): array {
        if (empty($parents)) {
            return $parents;
        }

        // Extract distinct parent IDs
        $parentIds = [];
        foreach ($parents as $parent) {
            $id = is_array($parent) ? ($parent[$parentPrimaryKey] ?? null) : ($parent->{$parentPrimaryKey} ?? null);
            if ($id !== null) {
                $parentIds[] = (int) $id;
            }
        }

        $parentIds = array_values(array_unique($parentIds));
        if (empty($parentIds)) {
            return $parents;
        }

        // Build parameterized IN placeholders
        $placeholders = [];
        $params = [];
        foreach ($parentIds as $index => $id) {
            $paramKey = ':parent_' . $index;
            $placeholders[] = $paramKey;
            $params[$paramKey] = $id;
        }
        $inClause = implode(', ', $placeholders);

        // Format column selections
        $selectCols = [];
        if (empty($columns) || (count($columns) === 1 && $columns[0] === '*')) {
            $selectClause = '*';
        } else {
            // Ensure foreign key is selected
            if (!in_array($foreignKey, $columns, true) && !in_array("*", $columns, true)) {
                $columns[] = $foreignKey;
            }
            foreach ($columns as $col) {
                $cleanCol = (string) $col;
                $selectCols[] = "\"{$cleanCol}\"";
            }
            $selectClause = implode(', ', $selectCols);
        }

        $tenantClause = '';
        if ($tenantId !== null) {
            $tenantClause = " AND \"{$tenantColumn}\" = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $sql = "SELECT {$selectClause} FROM \"{$childTable}\" WHERE \"{$foreignKey}\" IN ({$inClause}){$tenantClause}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $childRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group children by foreign key
        $grouped = [];
        foreach ($parentIds as $id) {
            $grouped[$id] = [];
        }

        foreach ($childRows as $row) {
            $parentId = (int) ($row[$foreignKey] ?? 0);
            $processedRow = $transformCallback !== null ? $transformCallback($row) : $row;
            $grouped[$parentId][] = $processedRow;
        }

        // Attach to parents
        foreach ($parents as &$parent) {
            $id = is_array($parent) ? ($parent[$parentPrimaryKey] ?? null) : ($parent->{$parentPrimaryKey} ?? null);
            $children = ($id !== null && isset($grouped[$id])) ? $grouped[$id] : [];

            if (is_array($parent)) {
                $parent[$relationKey] = $children;
            } elseif (is_object($parent)) {
                $parent->{$relationKey} = $children;
            }
        }
        unset($parent);

        return $parents;
    }

    /**
     * Eagerly loads a Many-to-Many relationship across an array of parent records using a pivot table.
     *
     * Execution Flow:
     * 1. Extract distinct parent primary keys.
     * 2. Perform a JOIN between pivot table and related table for all parent IDs in a single query.
     * 3. Group retrieved related records by pivot foreign key.
     * 4. Attach related collections to parents by reference.
     *
     * @param PDO $pdo Active Read PDO connection.
     * @param array<int, mixed> &$parents Parent collection.
     * @param string $pivotTable Pivot bridge database table name.
     * @param string $relatedTable Target related database table name.
     * @param string $foreignPivotKey Pivot column referencing parent entity.
     * @param string $relatedPivotKey Pivot column referencing related entity.
     * @param string $relationKey Property or array key to assign related entities to.
     * @param array<int, string> $columns Columns to select from related table.
     * @param int|null $tenantId Optional tenant ID.
     * @param string $parentPrimaryKey Primary key on parent entity (default 'id').
     * @param string $relatedPrimaryKey Primary key on related entity (default 'id').
     * @param callable|null $transformCallback Optional transformer callback.
     * @return array<int, mixed> Hydrated parents collection.
     */
    public static function loadManyToMany(
        PDO $pdo,
        array &$parents,
        string $pivotTable,
        string $relatedTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $relationKey,
        array $columns = ['*'],
        ?int $tenantId = null,
        string $parentPrimaryKey = 'id',
        string $relatedPrimaryKey = 'id',
        ?callable $transformCallback = null
    ): array {
        if (empty($parents)) {
            return $parents;
        }

        $parentIds = [];
        foreach ($parents as $parent) {
            $id = is_array($parent) ? ($parent[$parentPrimaryKey] ?? null) : ($parent->{$parentPrimaryKey} ?? null);
            if ($id !== null) {
                $parentIds[] = (int) $id;
            }
        }

        $parentIds = array_values(array_unique($parentIds));
        if (empty($parentIds)) {
            return $parents;
        }

        $placeholders = [];
        $params = [];
        foreach ($parentIds as $index => $id) {
            $paramKey = ':parent_' . $index;
            $placeholders[] = $paramKey;
            $params[$paramKey] = $id;
        }
        $inClause = implode(', ', $placeholders);

        $selectParts = ["pivot.\"{$foreignPivotKey}\" AS __eager_parent_id"];
        if (empty($columns) || (count($columns) === 1 && $columns[0] === '*')) {
            $selectParts[] = "rel.*";
        } else {
            foreach ($columns as $col) {
                $selectParts[] = "rel.\"{$col}\"";
            }
        }
        $selectClause = implode(', ', $selectParts);

        $tenantClause = '';
        if ($tenantId !== null) {
            $tenantClause = " AND rel.\"tenant_id\" = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $sql = "SELECT {$selectClause}
                FROM \"{$pivotTable}\" pivot
                INNER JOIN \"{$relatedTable}\" rel ON pivot.\"{$relatedPivotKey}\" = rel.\"{$relatedPrimaryKey}\"
                WHERE pivot.\"{$foreignPivotKey}\" IN ({$inClause}){$tenantClause}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($parentIds as $id) {
            $grouped[$id] = [];
        }

        foreach ($rows as $row) {
            $parentId = (int) $row['__eager_parent_id'];
            unset($row['__eager_parent_id']);
            $processedRow = $transformCallback !== null ? $transformCallback($row) : $row;
            $grouped[$parentId][] = $processedRow;
        }

        foreach ($parents as &$parent) {
            $id = is_array($parent) ? ($parent[$parentPrimaryKey] ?? null) : ($parent->{$parentPrimaryKey} ?? null);
            $children = ($id !== null && isset($grouped[$id])) ? $grouped[$id] : [];

            if (is_array($parent)) {
                $parent[$relationKey] = $children;
            } elseif (is_object($parent)) {
                $parent->{$relationKey} = $children;
            }
        }
        unset($parent);

        return $parents;
    }
}
