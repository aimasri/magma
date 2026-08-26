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
    public function get(string $key, mixed $default = null): mixed;
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
