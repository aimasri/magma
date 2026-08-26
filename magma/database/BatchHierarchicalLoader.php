<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;

/**
 * Title: Batch Hierarchical Loader
 *
 * Purpose:
 * - Execute batched recursive CTE queries and hydrate hierarchical trees into structured in-memory datasets.
 * - Group flat hierarchical SQL records by root ID and optionally assemble nested tree structures.
 *
 * Why / Why this design:
 * - Completely solves $O(N)$ N+1 queries when loading nested catalogs, bill of materials (BOM), 
 *   or multi-level modifier groups.
 * - By offloading recursive graph traversal to PostgreSQL and performing linear $O(M)$ grouping in PHP memory,
 *   we achieve maximum database throughput with minimal memory footprint.
 *
 * Teaching notes:
 * - `loadGroupedByRoot()` returns a dictionary mapping `[root_id => array of descendant rows]`.
 * - `assembleNestedTree()` transforms a flat list of parent-child rows into a nested recursive tree structure.
 */
class BatchHierarchicalLoader
{
    /**
     * Executes a batched recursive CTE query and groups the resulting rows by their originating root ID.
     *
     * Execution Flow:
     * 1. Check if $rootIds is empty; return empty array immediately if so.
     * 2. Build recursive CTE SQL and parameter bindings via CteQueryBuilder.
     * 3. Prepare and execute statement on the provided Read PDO connection.
     * 4. Fetch all associative rows.
     * 5. Iterate through results and group rows into an associative array indexed by `tree_root_id`.
     * 6. Return the grouped dictionary.
     *
     * @param PDO $pdo Active PDO connection (Read Replica).
     * @param string $table Target database table name.
     * @param array<int, int> $rootIds Array of root entity primary keys.
     * @param array<int, string> $columns Columns to select.
     * @param string $parentColumn Column referencing parent ID (default 'parent_id').
     * @param string $idColumn Primary key column (default 'id').
     * @param int|null $tenantId Optional tenant ID for multi-tenant isolation.
     * @param string $tenantColumn Tenant column name (default 'tenant_id').
     * @param int $maxDepth Maximum recursion depth limit (default 20).
     * @return array<int, array<int, array<string, mixed>>> Dictionary mapping root ID to array of descendant rows.
     */
    public static function loadGroupedByRoot(
        PDO $pdo,
        string $table,
        array $rootIds,
        array $columns = ['*'],
        string $parentColumn = 'parent_id',
        string $idColumn = 'id',
        ?int $tenantId = null,
        string $tenantColumn = 'tenant_id',
        int $maxDepth = 20
    ): array {
        if (empty($rootIds)) {
            return [];
        }

        $query = CteQueryBuilder::buildRecursiveTreeQuery(
            $table,
            $rootIds,
            $columns,
            $parentColumn,
            $idColumn,
            $tenantId,
            $tenantColumn,
            $maxDepth
        );

        $stmt = $pdo->prepare($query['sql']);
        $stmt->execute($query['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rootIds as $rootId) {
            $grouped[$rootId] = [];
        }

        foreach ($rows as $row) {
            $rootId = (int) ($row['tree_root_id'] ?? 0);
            $grouped[$rootId][] = $row;
        }

        return $grouped;
    }

    /**
     * Assembles a flat list of hierarchical rows into a nested tree structure using in-memory references.
     *
     * Execution Flow:
     * 1. Create lookup map indexed by row ID and initialize empty children array for each row.
     * 2. Iterate through items:
     *    a. If item has a parent ID present in the lookup map, append item by reference to parent's children.
     *    b. If item is a root node (no parent or parent not in set), append to top-level tree array.
     * 3. Return the nested tree array.
     *
     * Logic behind the logic:
     * - Reference-based tree construction operates in single-pass $O(N)$ linear time without recursion overhead.
     *
     * @param array<int, array<string, mixed>> $flatRows Flat list of database rows.
     * @param string $idKey Primary key field name (default 'id').
     * @param string $parentKey Parent foreign key field name (default 'parent_id').
     * @param string $childrenKey Array key to hold nested children (default 'children').
     * @return array<int, array<string, mixed>> Nested hierarchical tree.
     */
    public static function assembleNestedTree(
        array $flatRows,
        string $idKey = 'id',
        string $parentKey = 'parent_id',
        string $childrenKey = 'children'
    ): array {
        $tree = [];
        /** @var array<int|string, array<string, mixed>> $indexed */
        $indexed = [];

        foreach ($flatRows as $row) {
            $row[$childrenKey] = [];
            $id = $row[$idKey] ?? null;
            if (is_scalar($id)) {
                $indexed[(string)$id] = $row;
            }
        }

        foreach ($indexed as $id => &$node) {
            $parentId = $node[$parentKey] ?? null;
            if ($parentId !== null && is_scalar($parentId) && isset($indexed[(string)$parentId])) {
                if (is_array($indexed[(string)$parentId][$childrenKey] ?? null)) {
                    $indexed[(string)$parentId][$childrenKey][] = &$node;
                }
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }
}
