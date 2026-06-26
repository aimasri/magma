<?php

namespace Magma\config;

/**
 * Configuration Wrapper
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
    public function get(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    public function getRequired(string $key): string
    {
        return Config::getRequired($key);
    }

    public function getDatabaseSettings(): array
    {
        return Config::getDatabaseSettings();
    }

    public function getReplicaDatabaseSettings(): array
    {
        return Config::getReplicaDatabaseSettings();
    }

    public function getMailerSettings(): array
    {
        return Config::getMailerSettings();
    }
}
