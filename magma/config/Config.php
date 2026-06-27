<?php

namespace Magma\config;

/**
 * Configuration Registry
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
    private static array $env = [];

    /**
     * Loads environment variables from .env file and existing system environments.
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
     * Get a strictly required configuration value.
     * Throws an exception if the key is missing or empty.
     *
     * @param string $key
     * @return string
     * @throws \RuntimeException
     */
    public static function getRequired(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required configuration key: {$key}");
        }
        return (string) $value;
    }

    /**
     * Get the database connection settings.
     *
     * Provides a centralized place to manage DB defaults and environment fallbacks.
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
            'driver'   => self::get('DB_DRIVER', 'pgsql'),
            'host'     => self::get('DB_HOST', 'localhost'),
            'port'     => self::get('DB_PORT', '5432'),
            'dbname'   => self::getRequired('DB_NAME'),
            'user'     => self::getRequired('DB_USER'),
            'password' => self::getRequired('DB_PASSWORD'),
        ];
    }

    /**
     * Get the Replica Database Settings
     *
     * Purpose:
     * - Provides connection credentials specifically for read-only database replicas.
     *
     * Logic behind the logic:
     * - This seamlessly falls back to the primary master `DB_HOST` if no replica 
     *   is defined in the `.env` file. This is crucial because it allows the exact 
     *   same application codebase to run locally (with a single database container) 
     *   and in production (with a master and 5 read replicas) without throwing errors.
     *
     * @return array Associative array of connection settings.
     */
    public static function getReplicaDatabaseSettings(): array
    {
        return [
            'driver'   => self::get('DB_DRIVER', 'pgsql'),
            'host'     => self::get('DB_REPLICA_HOST', self::get('DB_HOST', 'localhost')),
            'port'     => self::get('DB_REPLICA_PORT', self::get('DB_PORT', '5432')),
            'dbname'   => self::get('DB_REPLICA_NAME', self::getRequired('DB_NAME')),
            'user'     => self::get('DB_REPLICA_USER', self::getRequired('DB_USER')),
            'password' => self::get('DB_REPLICA_PASSWORD', self::getRequired('DB_PASSWORD')),
        ];
    }

    /**
     * Get mailer configuration settings.
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
            'port'        => (int) self::get('MAIL_PORT', 2525),
            'username'    => self::getRequired('MAIL_USERNAME'),
            'password'    => self::getRequired('MAIL_PASSWORD'),
            'encryption'  => self::get('MAIL_ENCRYPTION', 'tls'),
            'from_email'  => self::getRequired('MAIL_FROM_ADDRESS'),
            'from_name'   => self::get('MAIL_FROM_NAME', 'Magma Framework'),
        ];
    }
}
