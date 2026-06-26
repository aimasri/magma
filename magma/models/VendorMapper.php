<?php

namespace Magma\models;

/**
 * Vendor Data Mapper
 *
 * Purpose:
 * - Encapsulate the translation between raw SQL row arrays and domain-ready PHP arrays.
 * - Manage the serialization and deserialization of JSON columns (like `theme_settings`).
 *
 * Why / Why this design:
 * - Extracting data hydration out of `VendorRepository` adheres to the Single 
 *   Responsibility Principle (SRP). The Repository handles purely persistence 
 *   (SQL queries), while the Mapper handles pure data transformation.
 *
 * Teaching notes:
 * - Centralizing the `ALLOWED_COLUMNS` inside the Mapper guarantees that both 
 *   INSERT and UPDATE operations use the exact same schema allow-list, preventing 
 *   malicious mass-assignment vulnerabilities.
 */
class VendorMapper
{
    private const ALLOWED_COLUMNS = [
        'name', 'email', 'tagline', 
        'plan_id', 'subscription_status', 'billing_cycle_anchor', 
        'payment_gateway_customer_id', 'theme_settings'
    ];

    /**
     * Hydrate a Raw Database Record into a Domain Array
     *
     * Purpose:
     * - Transforms the raw data coming from PostgreSQL into the clean array structure 
     *   expected by the domain layer.
     *
     * Logic behind the logic:
     * - PostgreSQL returns JSONB columns as JSON strings. By decoding this inside the 
     *   mapper, the application controllers and views can blindly treat `theme_settings` 
     *   as a standard nested array without knowing how it was persisted.
     *
     * @param array $vendor Raw associative array from PDO.
     * @return array The processed vendor array.
     */
    public function toDomain(array $vendor): array
    {
        $themeSettings = $vendor['theme_settings'] ?? null;

        if (is_string($themeSettings) && trim($themeSettings) !== '') {
            $decoded = json_decode($themeSettings, true);
            // Ensure the decoded result is strictly an array, bypassing scalar JSON values
            $vendor['theme_settings'] = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($themeSettings)) {
            $vendor['theme_settings'] = [];
        }

        return $vendor;
    }

    /**
     * Build parameter bindings for vendor INSERT and UPDATE statements.
     *
     * Purpose:
     * - DRY helper to extract recognized columns and automatically serialize JSON arrays.
     *
     * @param array $data Raw input array.
     * @return array Associative array of sanitized field bindings.
     */
    public function toDatabase(array $data): array
    {
        $bindings = [];
        foreach (self::ALLOWED_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column];
                
                if ($column === 'theme_settings') {
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value);
                    } elseif (!is_string($value)) {
                        // Fallback for nulls or other unhandled types
                        $value = '{}'; 
                    }
                }
                
                $bindings[$column] = $value;
            }
        }
        return $bindings;
    }
}
