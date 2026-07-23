<?php

namespace Magma\models;

use PDO;

/**
 * Base Repository
 *
 * Purpose:
 * - Provide a standard foundation for all repository classes in the application.
 * - Centralize the injection and assignment of Read and Write database connections.
 *
 * Why / Why this design:
 * - DRY Principle: Prevents every repository from duplicating the constructor boilerplate 
 *   required to accept both `dbWrite` and `dbRead` instances.
 * - SRP Principle: Repositories shouldn't be concerned with how connections are assigned, 
 *   only with utilizing them to fetch or mutate data.
 *
 * Teaching notes:
 * - Notice that the properties are `protected`, allowing child classes to access `$this->dbWrite` 
 *   and `$this->dbRead` directly without needing getter methods, keeping SQL queries clean.
 */
abstract class BaseRepository
{
    /**
     * @var PDO The master connection for INSERT, UPDATE, and DELETE queries.
     */
    protected PDO $dbWrite;

    /**
     * @var PDO The replica connection strictly for SELECT queries.
     */
    protected PDO $dbRead;

    /**
     * @param PDO $dbWrite
     * @param PDO $dbRead
     */
    public function __construct(PDO $dbWrite, PDO $dbRead)
    {
        $this->dbWrite = $dbWrite;
        $this->dbRead = $dbRead;
    }

    /**
     * Executes a single bulk INSERT statement.
     * 
     * @param string $table The table name.
     * @param array $columns An array of column names.
     * @param array $rows A multi-dimensional array of row values corresponding to the columns.
     */
    public function insertBulk(string $table, array $columns, array $rows): void
    {
        if (empty($rows) || empty($columns)) {
            return;
        }

        $colCount = count($columns);
        $rowCount = count($rows);

        $rowPlaceholders = '(' . implode(',', array_fill(0, $colCount, '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, $rowCount, $rowPlaceholders));
        $columnList = implode(', ', $columns);

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES {$allPlaceholders}";
        $stmt = $this->dbWrite->prepare($sql);

        $flatValues = [];
        foreach ($rows as $row) {
            foreach ($row as $val) {
                $flatValues[] = $val;
            }
        }

        $stmt->execute($flatValues);
    }
}
