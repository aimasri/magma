<?php

namespace Magma\config;

/**
 * Title: Configuration Wrapper
 *
 * Purpose:
 * - Implement the ConfigInterface by delegating to the static Config registry.
 *
 * Why / Why this design:
 * - This acts as a bridge between the static, procedural nature of reading environment 
 *   variables during application bootstrap, and the pure object-oriented Dependency 
 *   Inversion needs of domain services and testing.
 *
 * Teaching notes:
 * - The Adapter/Wrapper pattern is incredibly useful for integrating legacy or 
 *   static code into a modern, Dependency-Injection heavy architecture.
 */
class ConfigWrapper implements ConfigInterface
{
    /**
     * Get a configuration value by key, wrapped.
     *
     * Execution Flow:
     * 1. Delegates entirely to the static Config::get() method.
     *
     * Logic behind the logic:
     * - Preserves the static caching performance of Config while exposing it via an instance method.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    /**
     * Get a strictly required configuration value, wrapped.
     *
     * Execution Flow:
     * 1. Delegates entirely to the static Config::getRequired() method.
     *
     * Logic behind the logic:
     * - Allows services to request required variables without relying on static method calls directly.
     *
     * @param string $key
     * @return string
     * @throws \RuntimeException
     */
    public function getRequired(string $key): string
    {
        return Config::getRequired($key);
    }

    /**
     * Get the database connection settings, wrapped.
     *
     * Execution Flow:
     * 1. Delegates to Config::getDatabaseSettings().
     *
     * Logic behind the logic:
     * - Centralizes DB config retrieval via the interface.
     *
     * @return array{driver: string, host: string, port: string, dbname: string, user: string, password: string}
     */
    public function getDatabaseSettings(): array
    {
        return Config::getDatabaseSettings();
    }

    /**
     * Get the Replica Database Settings, wrapped.
     *
     * Execution Flow:
     * 1. Delegates to Config::getReplicaDatabaseSettings().
     *
     * Logic behind the logic:
     * - Hides the static nature of the replica config setup from consumers.
     *
     * @return array<string, mixed>
     */
    public function getReplicaDatabaseSettings(): array
    {
        return Config::getReplicaDatabaseSettings();
    }

    /**
     * Get mailer configuration settings, wrapped.
     *
     * Execution Flow:
     * 1. Delegates to Config::getMailerSettings().
     *
     * Logic behind the logic:
     * - Enables mockable mailer settings for tests.
     *
     * @return array{host: string, port: int, username: string, password: string, encryption: string, from_email: string, from_name: string}
     */
    public function getMailerSettings(): array
    {
        return Config::getMailerSettings();
    }
}
