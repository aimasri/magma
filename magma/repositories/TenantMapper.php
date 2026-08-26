<?php

namespace Magma\repositories;

/**
 * Title: Tenant Data Mapper
 *
 * Purpose:
 * - Encapsulate the translation between raw SQL row arrays and domain-ready PHP arrays.
 * - Manage the serialization and deserialization of JSON columns (like `theme_settings`).
 *
 * Why / Why this design:
 * - Extracting data hydration out of `TenantRepository` adheres to the Single 
 *   Responsibility Principle (SRP). The Repository handles purely persistence 
 *   (SQL queries), while the Mapper handles pure data transformation.
 *
 * Teaching notes:
 * - Centralizing the `ALLOWED_COLUMNS` inside the Mapper guarantees that both 
 *   INSERT and UPDATE operations use the exact same schema allow-list, preventing 
 *   malicious mass-assignment vulnerabilities.
 */
class TenantMapper
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
     * @param array<string, mixed> $tenant Raw associative array from PDO.
     * @return \Magma\dto\TenantDTO The processed tenant array.
     */
    public function toDomain(array $tenant): \Magma\dto\TenantDTO
    {
        foreach (self::JSON_COLUMNS as $jsonCol) {
            $val = $tenant[$jsonCol] ?? null;
            if (is_string($val) && trim($val) !== '') {
                $decoded = json_decode($val, true);
                $tenant[$jsonCol] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($val)) {
                $tenant[$jsonCol] = [];
            }
        }

        $id = isset($tenant['id']) && is_scalar($tenant['id']) ? (int) $tenant['id'] : 0;
        $name = isset($tenant['name']) && is_scalar($tenant['name']) ? (string) $tenant['name'] : '';
        $tagline = isset($tenant['tagline']) && is_scalar($tenant['tagline']) ? (string) $tenant['tagline'] : null;
        $email = isset($tenant['email']) && is_scalar($tenant['email']) ? (string) $tenant['email'] : '';
        $planId = isset($tenant['plan_id']) && is_scalar($tenant['plan_id']) ? (int) $tenant['plan_id'] : 0;
        $subStatus = isset($tenant['subscription_status']) && is_scalar($tenant['subscription_status']) ? (string) $tenant['subscription_status'] : '';
        $billingCycle = isset($tenant['billing_cycle_anchor']) && is_scalar($tenant['billing_cycle_anchor']) ? (string) $tenant['billing_cycle_anchor'] : null;
        $paymentGateway = isset($tenant['payment_gateway_customer_id']) && is_scalar($tenant['payment_gateway_customer_id']) ? (string) $tenant['payment_gateway_customer_id'] : null;
        $themeSettings = is_array($tenant['theme_settings']) ? $tenant['theme_settings'] : [];

        return new \Magma\dto\TenantDTO(
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
     * Build parameter bindings for tenant INSERT and UPDATE statements.
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
