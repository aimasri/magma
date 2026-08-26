<?php

namespace Magma\config;

/**
 * Title: Configuration Registry
 *
 * Purpose:
 * - Centralize environment variable access.
 * - Initialize global settings and provide fallback defaults.
 *
 * Why / Why this design:
 * - Implements the Registry pattern. Rather than scattering `getenv()` or `$_ENV` calls 
 *   throughout the codebase, this ensures a single, predictable source of truth. It allows 
 *   environment variables to be mocked or overridden easily during testing.
 *
 * Teaching notes:
 * - We utilize a custom `DotEnvParser` to read from the `.env` file, keeping dependencies lightweight.
 * - In a massive production system, configuration values should be cached 
 *   as PHP arrays to prevent file I/O overhead on every request.
 */
class Config
{
    /** @var array<string, mixed> */
    private static array $env = [];

    /**
     * Loads environment variables from .env file and existing system environments.
     *
     * Execution Flow:
     * 1. Parses the provided .env file using DotEnvParser.
     * 2. Iterates through the parsed key-value pairs.
     * 3. Sets the values into the internal cache, system environment via `putenv`, and `$_ENV`.
     *
     * Logic behind the logic:
     * - Seeding `$_ENV` and `putenv` ensures that older legacy components or 
     *   third-party libraries that rely on standard PHP environment functions 
     *   can still access the configuration seamlessly.
     *
     * @param string $envPath Path to the .env file.
     * @return void
     */
    public static function initialize(string $envPath = __DIR__ . '/../../.env'): void
    {
        $parsedEnv = DotEnvParser::parse($envPath);
        foreach ($parsedEnv as $key => $value) {
            if (!isset(self::$env[$key])) {
                self::$env[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Get a configuration value by key.
     *
     * Execution Flow:
     * 1. Check if the value exists in the internal cache (populated from the .env file).
     * 2. Fallback to `$_ENV` for server-provided variables.
     * 3. Fallback to `getenv()` for system-level variables.
     * 4. Cache the resolved value to prevent repeated system calls.
     * 
     * Logic behind the logic:
     * - Retrieving variables lazily on-demand is O(1) and prevents iterating over 
     *   the entire system environment block, reducing memory allocation and CPU 
     *   cycles during the application bootstrap phase.
     *
     * @param string $key The configuration key (e.g., 'DB_HOST').
     * @param mixed $default The default value if the key is not found.
     * @return mixed The configuration value or the default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }

        // Prioritize $_ENV then fall back to getenv()
        $val = $_ENV[$key] ?? getenv($key);
        
        if ($val !== false && $val !== null) {
            self::$env[$key] = $val; // Cache the value for subsequent reads
            return $val;
        }

        return $default;
    }

    /**
     * Retrieves a configuration value as a string safely.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function getString(string $key, string $default = ''): string
    {
        $val = self::get($key, $default);
        return is_scalar($val) ? (string)$val : $default;
    }

    /**
     * Retrieves a configuration value as an integer safely.
     *
     * @param string $key
     * @param int $default
     * @return int
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key, $default);
        return is_scalar($val) ? (int)$val : $default;
    }

    /**
     * Get a strictly required configuration value.
     * Throws an exception if the key is missing or empty.
     *
     * Execution Flow:
     * 1. Calls the standard `get()` method to retrieve the value.
     * 2. Checks if the returned value is null or an empty string.
     * 3. Throws a RuntimeException if the check fails, otherwise returns the casted string.
     *
     * Logic behind the logic:
     * - Enforces fail-fast principles during system boot or request initialization, 
     *   preventing confusing errors deeper in the application where the missing 
     *   value would eventually cause a failure.
     *
     * @param string $key The configuration key.
     * @return string The configuration value.
     * @throws \RuntimeException If the key is not found or empty.
     */
    public static function getRequired(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required configuration key: {$key}");
        }
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Get the database connection settings.
     *
     * Execution Flow:
     * 1. Retrieves required credentials (DB_NAME, DB_USER, DB_PASSWORD).
     * 2. Retrieves optional settings with safe defaults (DB_DRIVER, DB_HOST, DB_PORT).
     * 3. Returns the aggregated array.
     *
     * Logic behind the logic:
     * - Provides a centralized place to manage DB defaults and environment fallbacks, 
     *   preventing scatter of default values across the repository/database layer.
     *
     * @return array{
     *   driver: string,
     *   host: string,
     *   port: string,
     *   dbname: string,
     *   user: string,
     *   password: string
     * }
     */
    public static function getDatabaseSettings(): array
    {
        return [
            'driver'   => self::getString('DB_DRIVER', 'pgsql'),
            'host'     => self::getString('DB_HOST', 'localhost'),
            'port'     => self::getString('DB_PORT', '5432'),
            'dbname'   => self::getRequired('DB_NAME'),
            'user'     => self::getRequired('DB_USER'),
            'password' => self::getRequired('DB_PASSWORD'),
        ];
    }

    /**
     * Get the Replica Database Settings.
     *
     * Purpose:
     * - Provides connection credentials specifically for read-only database replicas.
     *
     * Execution Flow:
     * 1. Attempts to retrieve replica-specific configuration variables.
     * 2. Provides the primary master configuration variables as fallbacks if replica variables are not set.
     * 3. Returns the aggregated array.
     *
     * Logic behind the logic:
     * - This seamlessly falls back to the primary master `DB_HOST` if no replica 
     *   is defined in the `.env` file. This is crucial because it allows the exact 
     *   same application codebase to run locally (with a single database container) 
     *   and in production (with a master and read replicas) without throwing errors.
     *
     * @return array<string, mixed> Associative array of connection settings.
     */
    public static function getReplicaDatabaseSettings(): array
    {
        return [
            'driver'   => self::getString('DB_DRIVER', 'pgsql'),
            'host'     => self::getString('DB_REPLICA_HOST', self::getString('DB_HOST', 'localhost')),
            'port'     => self::getString('DB_REPLICA_PORT', self::getString('DB_PORT', '5432')),
            'dbname'   => self::getString('DB_REPLICA_NAME', self::getRequired('DB_NAME')),
            'user'     => self::getString('DB_REPLICA_USER', self::getRequired('DB_USER')),
            'password' => self::getString('DB_REPLICA_PASSWORD', self::getRequired('DB_PASSWORD')),
        ];
    }

    /**
     * Get mailer configuration settings.
     *
     * Execution Flow:
     * 1. Collects all mailer-related environment variables.
     * 2. Enforces required fields for critical connection data.
     * 3. Returns a structured associative array for the mailer transport service.
     *
     * Logic behind the logic:
     * - Grouping these settings into a single configuration payload reduces parameter 
     *   clutter when initializing the mailer service.
     *
     * @return array{
     *   host: string,
     *   port: int,
     *   username: string,
     *   password: string,
     *   encryption: string,
     *   from_email: string,
     *   from_name: string
     * }
     */
    public static function getMailerSettings(): array
    {
        return [
            'host'        => self::getRequired('MAIL_HOST'),
            'port'        => self::getInt('MAIL_PORT', 2525),
            'username'    => self::getRequired('MAIL_USERNAME'),
            'password'    => self::getRequired('MAIL_PASSWORD'),
            'encryption'  => self::getString('MAIL_ENCRYPTION', 'tls'),
            'from_email'  => self::getRequired('MAIL_FROM_ADDRESS'),
            'from_name'   => self::getString('MAIL_FROM_NAME', 'Magma Framework'),
        ];
    }
}
