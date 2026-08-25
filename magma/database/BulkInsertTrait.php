<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;

/**
 * Title: Bulk Insert Trait
 * Purpose:
 * - Provides high-performance mass-insertion capabilities to command repositories.
 * Why/Why this design:
 * - Extracted from legacy BaseRepository to adhere to CQRS and trait-based composition.
 * Teaching notes:
 * - Uses dynamic chunking to avoid PDO parameter limits.
 */
trait BulkInsertTrait
{
    /**
     * Executes a single bulk INSERT statement.
     *
     * Execution Flow:
     * 1. Calculate safe chunk sizes based on PDO parameter limits.
     * 2. Split rows into chunks.
     * 3. Begin a nested or root transaction.
     * 4. For each chunk, build a batched INSERT statement.
     * 5. Flatten the chunk values and execute the statement.
     * 6. Commit if it's the root transaction, or rollback on failure.
     *
     * Logic behind the logic:
     * - Uses dynamic chunking to avoid exceeding the maximum number of bound parameters allowed by PDO in a single query.
     * - Managing transactions ensures partial inserts are rolled back, maintaining database integrity.
     */
    public function insertBulk(string $table, array $columns, array $rows, int $chunkSize = 500): void
    {
        if (empty($rows) || empty($columns)) {
            return;
        }

        $colCount = count($columns);
        $escapedColumns = array_map(fn($col) => '"' . str_replace('"', '""', $col) . '"', $columns);
        $columnList = implode(', ', $escapedColumns);
        $escapedTable = '"' . str_replace('"', '""', $table) . '"';

        $dbWrite = $this->getDb(); // Assuming command repository has getDb() representing dbWrite
        $isNested = $dbWrite->inTransaction();
        if (!$isNested) {
            $dbWrite->beginTransaction();
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
                $stmt = $dbWrite->prepare($sql);
                
                $flatValues = [];
                foreach ($chunk as $row) {
                    foreach ($row as $val) {
                        $flatValues[] = $val;
                    }
                }
                
                $stmt->execute($flatValues);
            }

            if (!$isNested) {
                $dbWrite->commit();
            }
        } catch (\Throwable $e) {
            if (!$isNested && $dbWrite->inTransaction()) {
                $dbWrite->rollBack();
            }
            throw $e;
        }
    }
}
