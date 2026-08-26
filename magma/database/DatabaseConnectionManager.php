<?php

namespace Magma\database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Title: Database Connection Manager
 *
 * Purpose:
 * - Provide lazily-initialized PDO connection instances for Read/Write splitting.
 * - Enforce safe connection defaults (exceptions, associative fetches).
 *
 * Why / Why this design:
 * - Implements the Dependency Injection pattern specifically for the database connection. This ensures 
 *   the application doesn't exhaust the database by opening a new connection for every 
 *   repository that needs data during a single HTTP request, while remaining fully testable 
 *   and decoupled from static global state.
 *
 * Teaching notes:
 * - When using PgBouncer in `transaction` mode, native prepared statements cause 
 *   "prepared statement does not exist" errors. Therefore, we explicitly set 
 *   `PDO::ATTR_EMULATE_PREPARES => true` so PHP handles the preparation safely 
 *   before sending the raw SQL string over the socket.
 */
class DatabaseConnectionManager
{
    private ?PDO $writeInstance = null;
    private ?PDO $readInstance = null;

    /** @var array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} */
    private array $writeSettings;
    /** @var array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} */
    private array $readSettings;
    private bool $emulatePrepares;

    private bool $forceWrite = false;

    /**
     * @param array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} $writeSettings
     * @param array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} $readSettings
     * @param bool $emulatePrepares
     */
    public function __construct(array $writeSettings, array $readSettings, bool $emulatePrepares = false)
    {
        $this->writeSettings = $writeSettings;
        $this->readSettings = $readSettings;
        $this->emulatePrepares = $emulatePrepares;
    }

    public function forceWriteForReads(bool $force): void
    {
        $this->forceWrite = $force;
    }

    /**
     * Returns the active PDO connection instance for Write (Master) operations.
     * 
     * Execution Flow:
     * 1. Check if `$writeInstance` already holds a connection. If so, return it.
     * 2. If null, delegate to `createConnection()` to instantiate the PDO object using write settings.
     * 3. Cache and return the instance.
     *
     * Logic behind the logic:
     * - Lazy initialization ensures we don't open a write connection unless the current 
     *   request actually attempts to mutate data.
     */
    public function getWriteConnection(): PDO
    {
        if ($this->writeInstance !== null) {
            return $this->writeInstance;
        }

        $this->writeInstance = $this->createConnection($this->writeSettings);
        
        return $this->writeInstance;
    }

    /**
     * Returns the active PDO connection instance for Read (Replica) operations.
     * 
     * Execution Flow:
     * 1. Check if `$readInstance` already holds a connection. If so, return it.
     * 2. If null, delegate to `createConnection()` to instantiate the PDO object using read settings.
     * 3. Cache and return the instance.
     *
     * Logic behind the logic:
     * - By separating reads and writes at the connection level, we can horizontally 
     *   scale our database reads across multiple replica nodes without altering 
     *   the core application logic.
     */
    public function getReadConnection(): PDO
    {
        if ($this->forceWrite) {
            return $this->getWriteConnection();
        }

        if ($this->readInstance !== null) {
            return $this->readInstance;
        }

        $this->readInstance = $this->createConnection($this->readSettings);

        return $this->readInstance;
    }

    /**
     * Helper to construct the PDO connection.
     * 
     * Execution Flow:
     * 1. Construct the Data Source Name (DSN) string from the provided settings.
     * 2. Instantiate a new PDO object, applying strict error reporting and emulated prepared statements.
     * 3. Catch any PDOException and rethrow as a generic RuntimeException.
     * 
     * Logic behind the logic:
     * - Wrapping the creation in a `try/catch` prevents raw PDO exception strings 
     *   (which may contain passwords) from accidentally leaking to the frontend.
     *
     * @param array{driver?: string, host: string, port: int|string, dbname: string, user: string, password: string} $settings The connection settings.
     * @return PDO The configured connection.
     */
    private function createConnection(array $settings): PDO
    {
        $driver = $settings['driver'] ?? 'pgsql';
        $dsn = "{$driver}:host={$settings['host']};port={$settings['port']};dbname={$settings['dbname']};";

        try {
            return new PDO($dsn, $settings['user'], $settings['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => $this->emulatePrepares,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed. Please check your configuration.", 0, $e);
        }
    }

    /**
     * Closes the active database connections.
     * 
     * Setting the PDO instances to null forces PHP to decrement the reference count,
     * which implicitly closes the underlying socket connections to the database.
     * Essential for long-lived workers to prevent connection pool exhaustion.
     */
    public function disconnect(): void
    {
        $this->writeInstance = null;
        $this->readInstance = null;
    }
}
