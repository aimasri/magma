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
}
