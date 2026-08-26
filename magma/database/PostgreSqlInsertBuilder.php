<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Title: PostgreSQL Atomic Insert Query Builder
 *
 * Purpose:
 * - Build and execute PostgreSQL `INSERT` statements with atomic primary key resolution using `RETURNING id`.
 * - Eliminate sequence-coupling and `PDO::lastInsertId()` failures across PostgreSQL connections.
 *
 * Why / Why this design:
 * - In PostgreSQL via PDO, calling `PDO::lastInsertId()` without an explicit sequence name often returns 
 *   `0` or `false` (especially when PgBouncer or table partitioning is used). 
 * - Utilizing PostgreSQL's native `RETURNING "id"` clause guarantees atomic, thread-safe, and driver-safe 
 *   primary key retrieval in a single database roundtrip without race conditions.
 *
 * Teaching notes:
 * - Identifier names (table and columns) are enclosed in double quotes (`"`) adhering to ANSI SQL / PostgreSQL standard.
 * - Values are bound as prepared statement parameters, preventing SQL injection vulnerabilities.
 */
class PostgreSqlInsertBuilder
{
    /**
     * Constructs a parameterized PostgreSQL INSERT query with a RETURNING clause.
     *
     * Execution Flow:
     * 1. Validate that the data array is not empty.
     * 2. Quote the table name and each column name with double quotes.
     * 3. Construct named parameter placeholders for each value.
     * 4. Append the `RETURNING "{$primaryKey}"` clause to the SQL string.
     * 5. Return an associative array containing the generated SQL and parameter map.
     *
     * @param string $table Target database table name.
     * @param array<string, mixed> $data Associative array of column => value pairs.
     * @param string $primaryKey Column name of the primary key to return (default 'id').
     * @return array{sql: string, params: array<string, mixed>}
     * @throws InvalidArgumentException If $data is empty.
     */
    public static function build(string $table, array $data, string $primaryKey = 'id'): array
    {
        if (empty($data)) {
            throw new InvalidArgumentException("Cannot build INSERT statement for table [{$table}] with empty data array.");
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $column => $value) {
            // Strip any non-alphanumeric/underscore characters to completely eliminate SQL injection vectors.
            $cleanCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column) ?? '';
            $paramKey = ':ins_' . $cleanCol;
            
            $columns[] = "\"{$cleanCol}\"";
            $placeholders[] = $paramKey;
            $params[$paramKey] = $value;
        }

        $columnsList = implode(', ', $columns);
        $placeholdersList = implode(', ', $placeholders);

        $sql = "INSERT INTO \"{$table}\" ({$columnsList}) VALUES ({$placeholdersList}) RETURNING \"{$primaryKey}\"";

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * Executes the INSERT query on the provided PDO connection and returns the generated primary key.
     *
     * Execution Flow:
     * 1. Build the SQL and parameter bindings via build().
     * 2. Prepare the PDO statement.
     * 3. Execute the statement with the parameters.
     * 4. Fetch the returned primary key column using `$stmt->fetchColumn()`.
     * 5. Return the cast integer ID.
     *
     * Logic behind the logic:
     * - Statement column fetching (`$stmt->fetchColumn()`) directly reads the row returned by `RETURNING`, 
     *   bypassing driver sequence lookup.
     *
     * @param PDO $pdo Active PDO database connection (Write connection).
     * @param string $table Target database table name.
     * @param array<string, mixed> $data Associative column => value pairs.
     * @param string $primaryKey Primary key column name (default 'id').
     * @return int The generated primary key ID.
     * @throws RuntimeException If execution fails or no primary key was returned.
     */
    public static function insertAndGetId(PDO $pdo, string $table, array $data, string $primaryKey = 'id'): int
    {
        $built = self::build($table, $data, $primaryKey);
        
        $stmt = $pdo->prepare($built['sql']);
        $stmt->execute($built['params']);

        $id = $stmt->fetchColumn(0);
        if ($id === false || $id === null) {
            throw new RuntimeException("Failed to retrieve generated primary key [{$primaryKey}] from table [{$table}].");
        }

        return (int) $id;
    }
}
