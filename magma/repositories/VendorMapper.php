<?php

namespace Magma\repositories;

/**
 * Title: Vendor Data Mapper
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

    private const JSON_COLUMNS = [
        'theme_settings'
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
     * @param array<string, mixed> $vendor Raw associative array from PDO.
     * @return \Magma\dto\VendorDTO The processed vendor array.
     */
    public function toDomain(array $vendor): \Magma\dto\VendorDTO
    {
        foreach (self::JSON_COLUMNS as $jsonCol) {
            $val = $vendor[$jsonCol] ?? null;
            if (is_string($val) && trim($val) !== '') {
                $decoded = json_decode($val, true);
                $vendor[$jsonCol] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($val)) {
                $vendor[$jsonCol] = [];
            }
        }

        $id = isset($vendor['id']) && is_scalar($vendor['id']) ? (int) $vendor['id'] : 0;
        $name = isset($vendor['name']) && is_scalar($vendor['name']) ? (string) $vendor['name'] : '';
        $tagline = isset($vendor['tagline']) && is_scalar($vendor['tagline']) ? (string) $vendor['tagline'] : null;
        $email = isset($vendor['email']) && is_scalar($vendor['email']) ? (string) $vendor['email'] : '';
        $planId = isset($vendor['plan_id']) && is_scalar($vendor['plan_id']) ? (int) $vendor['plan_id'] : 0;
        $subStatus = isset($vendor['subscription_status']) && is_scalar($vendor['subscription_status']) ? (string) $vendor['subscription_status'] : '';
        $billingCycle = isset($vendor['billing_cycle_anchor']) && is_scalar($vendor['billing_cycle_anchor']) ? (string) $vendor['billing_cycle_anchor'] : null;
        $paymentGateway = isset($vendor['payment_gateway_customer_id']) && is_scalar($vendor['payment_gateway_customer_id']) ? (string) $vendor['payment_gateway_customer_id'] : null;
        $themeSettings = is_array($vendor['theme_settings']) ? $vendor['theme_settings'] : [];

        return new \Magma\dto\VendorDTO(
            id: $id,
            name: $name,
            tagline: $tagline,
            email: $email,
            plan_id: $planId,
            subscription_status: $subStatus,
            billing_cycle_anchor: $billingCycle,
            payment_gateway_customer_id: $paymentGateway,
            theme_settings: $themeSettings
        );
    }

    /**
     * Build parameter bindings for vendor INSERT and UPDATE statements.
     *
     * Purpose:
     * - DRY helper to extract recognized columns and automatically serialize JSON arrays.
     *
     * Execution Flow:
     * 1. Iterate over the statically defined `ALLOWED_COLUMNS`.
     * 2. If the column exists in the provided `$data`, process its value.
     * 3. For `theme_settings`, serialize arrays/objects into JSON strings.
     * 4. Return the sanitized map of column bindings.
     *
     * Logic behind the logic:
     * - Restricting bindings explicitly to `ALLOWED_COLUMNS` serves as a fail-safe firewall 
     *   against malicious mass-assignment, ensuring internal fields (like `id`) cannot be 
     *   injected from an HTTP POST request.
     *
     * @param array<string, mixed> $data Raw input array.
     * @return array<string, mixed> Associative array of sanitized field bindings.
     */
    public function toDatabase(array $data): array
    {
        $bindings = [];
        foreach (self::ALLOWED_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];
            
            if (in_array($column, self::JSON_COLUMNS)) {
                $value = match (true) {
                    is_array($value), is_object($value) => json_encode($value),
                    is_string($value) => $value,
                    default => '{}'
                };
            }
            
            $bindings[$column] = $value;
        }
        return $bindings;
    }
}
