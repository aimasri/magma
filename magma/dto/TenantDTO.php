<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: Tenant Data Transfer Object
 *
 * Purpose:
 * - Encapsulates tenant identity, subscription state, and white-label JSONB metadata (`theme_settings`) into a strictly typed, immutable value structure.
 * - Provides type-safe white-label metadata accessors (`setting()`, `settingBool()`, `settingArray()`, `hasSetting()`) so views and services never branch on hardcoded `tenant_id` integers.
 *
 * Why / Why this design:
 * - Native PHP 8.2+ `readonly class` guarantees structural immutability across repository, middleware, and presentation boundaries.
 * - Exposing typed accessors with dot-notation support over `$theme_settings` allows multi-tenant and white-label deployments to customize contact addresses, logos, palettes, and copy purely from database metadata.
 *
 * Teaching notes:
 * - Never write `if ($tenant->id === 2)` in templates or controllers. Store brand-specific values in `tenants.theme_settings` JSONB and read them via `$tenant->setting('orders_email', 'orders@example.com')`.
 */
readonly class TenantDTO
{
    /**
     * Constructs an immutable Tenant Data Transfer Object.
     *
     * @param int $id
     * @param string $name
     * @param string|null $tagline
     * @param string $email
     * @param int $plan_id
     * @param string $subscription_status
     * @param string|null $billing_cycle_anchor
     * @param string|null $payment_gateway_customer_id
     * @param array<string, mixed> $theme_settings
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $tagline,
        public string $email,
        public int $plan_id,
        public string $subscription_status,
        public ?string $billing_cycle_anchor,
        public ?string $payment_gateway_customer_id,
        public array $theme_settings = []
    ) {}

    /**
     * Checks whether a non-null white-label setting exists under the given flat or dot-notated key.
     *
     * @param string $key Flat key (e.g. `'orders_email'`) or dot path (e.g. `'footer.description'`).
     * @return bool
     */
    public function hasSetting(string $key): bool
    {
        return $this->resolveSettingValue($key) !== null;
    }

    /**
     * Retrieves a string white-label setting from `$theme_settings`, falling back to `$default` when absent or empty.
     *
     * @param string $key Flat key or dot-notated path into `$theme_settings`.
     * @param string $default Fallback string when the key is missing or blank.
     * @return string
     */
    public function setting(string $key, string $default = ''): string
    {
        $val = $this->resolveSettingValue($key);
        if (!is_scalar($val)) {
            return $default;
        }

        $str = trim((string) $val);
        return $str !== '' ? $str : $default;
    }

    /**
     * Retrieves a boolean white-label setting from `$theme_settings`, falling back to `$default` when absent.
     *
     * @param string $key Flat key or dot-notated path into `$theme_settings`.
     * @param bool $default Fallback boolean when the key is missing.
     * @return bool
     */
    public function settingBool(string $key, bool $default = false): bool
    {
        $val = $this->resolveSettingValue($key);
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val) || is_float($val)) {
            return (bool) $val;
        }
        if (is_string($val)) {
            $normalized = strtolower(trim($val));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }
        return $default;
    }

    /**
     * Retrieves a nested array setting from `$theme_settings`, returning an empty array when absent or non-array.
     *
     * @param string $key Flat key or dot-notated path into `$theme_settings`.
     * @return array<int|string, mixed>
     */
    public function settingArray(string $key): array
    {
        $val = $this->resolveSettingValue($key);
        return is_array($val) ? $val : [];
    }

    /**
     * Resolves a raw value from `$theme_settings` supporting both literal keys and dot-notation traversal.
     *
     * @param string $key
     * @return mixed
     */
    private function resolveSettingValue(string $key): mixed
    {
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->theme_settings)) {
            return $this->theme_settings[$key];
        }

        if (!str_contains($key, '.')) {
            return null;
        }

        $segments = explode('.', $key);
        $cursor = $this->theme_settings;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
