<?php

namespace Magma\config;

/**
 * Title: Configuration Interface
 *
 * Purpose:
 * - Define the contract for configuration retrieval.
 *
 * Why / Why this design:
 * - By providing an interface, we can inject a mockable config object into services 
 *   during unit testing, allowing for environment-specific testing without mutating 
 *   global static state.
 *
 * Teaching notes:
 * - When writing tests, you can pass a simple anonymous class or mock object 
 *   that implements this interface, rather than relying on `.env` files.
 */
interface ConfigInterface
{
    /**
     * Retrieves a configuration value by its key, returning a default if not found.
     *
     * Logic behind the logic:
     * - Provides a safe way to access configuration values without triggering errors for missing keys.
     *   This prevents application crashes during deployments when new, non-critical config keys are introduced.
     *
     * @param string $key The configuration key.
     * @param mixed $default The fallback value if the key does not exist.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieves a configuration value by its key, throwing an exception if the key is missing.
     *
     * Logic behind the logic:
     * - Enforces a strict contract for critical application settings (e.g., database credentials).
     *   Failing fast on startup or request initialization prevents unpredictable behavior downstream.
     *
     * @param string $key The required configuration key.
     * @return string
     * @throws \RuntimeException If the key is not found or is empty.
     */
    public function getRequired(string $key): string;
    /**
     * @return array{driver: string, host: string, port: string, dbname: string, user: string, password: string}
     */
    public function getDatabaseSettings(): array;

    /**
     * @return array<string, mixed>
     */
    public function getReplicaDatabaseSettings(): array;

    /**
     * @return array{host: string, port: int, username: string, password: string, encryption: string, from_email: string, from_name: string}
     */
    public function getMailerSettings(): array;
}
