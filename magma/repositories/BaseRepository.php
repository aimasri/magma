<?php

declare(strict_types=1);

namespace Magma\repositories;

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
    protected \Magma\database\DatabaseConnectionManager $dbManager;

    public function __construct(\Magma\database\DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    protected function getDbWrite(): \PDO
    {
        return $this->dbManager->getWriteConnection();
    }

    protected function getDbRead(): \PDO
    {
        return $this->dbManager->getReadConnection();
    }

    /**
     * Executes a single bulk INSERT statement.
     * 
     * Purpose:
     * - Mass-inserts records into the database with high efficiency.
     *
     * Execution Flow:
     * 1. Split the massive `$rows` array into safe chunk sizes to avoid query limits.
     * 2. Begin a transaction on the Write connection.
     * 3. Dynamically construct a massive parameterized INSERT string for each chunk.
     * 4. Execute the insert, commit if all chunks succeed, rollback on failure.
     *
     * Logic behind the logic:
     * - Using explicit chunking and a single transaction massively reduces round-trips 
     *   to the database. The 65,000 parameter limit calculation guarantees we never hit 
     *   PDO's internal placeholder ceiling.
     *
     * @param string $table The table name.
     * @param array $columns An array of column names.
     * @param array $rows A multi-dimensional array of row values corresponding to the columns.
     * @param int $chunkSize The number of rows to insert per statement.
     */
    public function insertBulk(string $table, array $columns, array $rows, int $chunkSize = 500): void
    {
        if (empty($rows) || empty($columns)) {
            return;
        }

        $colCount = count($columns);
        $escapedColumns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", $columns);
        $columnList = implode(', ', $escapedColumns);
        $escapedTable = "`" . str_replace("`", "``", $table) . "`";

        $isNested = $this->getDbWrite()->inTransaction();
        if (!$isNested) {
            $this->getDbWrite()->beginTransaction();
        }
        
        try {
            $maxAllowedChunk = (int) floor(65000 / $colCount);
            $safeChunkSize = min($chunkSize, $maxAllowedChunk);
            
            $chunks = array_chunk($rows, $safeChunkSize);
            
            foreach ($chunks as $chunk) {
                $rowCount = count($chunk);
                $rowPlaceholders = '(' . implode(',', array_fill(0, $colCount, '?')) . ')';
                $allPlaceholders = implode(',', array_fill(0, $rowCount, $rowPlaceholders));
                
                $sql = "INSERT INTO {$escapedTable} ({$columnList}) VALUES {$allPlaceholders}";
                $stmt = $this->getDbWrite()->prepare($sql);
                
                $flatValues = [];
                foreach ($chunk as $row) {
                    foreach ($row as $val) {
                        $flatValues[] = $val;
                    }
                }
                
                $stmt->execute($flatValues);
            }

            if (!$isNested) {
                $this->getDbWrite()->commit();
            }
        } catch (\Throwable $e) {
            if (!$isNested && $this->getDbWrite()->inTransaction()) {
                $this->getDbWrite()->rollBack();
            }
            throw $e;
        }
    }
}
