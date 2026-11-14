<?php

declare(strict_types=1);

namespace Magma\security;

use InvalidArgumentException;

/**
 * Title: Authentication Tenant Scoping Mode
 *
 * Purpose:
 * - Defines the architectural strategy governing how user authentication lookups and HTTP session tenant contexts interact across multi-tenant deployments.
 * - Supports both strict per-domain white-label isolation (`tenant_scoped`) and shared central SaaS login portals (`global_gateway`).
 *
 * Why / Why this design:
 * - Native PHP 8.2+ Backed Enum: Eliminates magic strings and boolean flags while encoding explicit domain semantics at the type system boundary.
 * - Multi-Tenant Architectural Flexibility: White-label platforms map every tenant to a distinct domain and isolate user lookups per host tenant, whereas single-portal SaaS applications authenticate globally unique email identities at a shared gateway domain and then bind `TenantContext` to the authenticated user's verified `tenant_id`.
 *
 * Teaching notes:
 * - Configure `AUTH_TENANT_MODE=tenant_scoped` (the default) when tenants operate on isolated custom domains.
 * - Configure `AUTH_TENANT_MODE=global_gateway` only when `users.email` is globally unique across the database and multiple tenants log in through a single shared portal hostname.
 */
enum AuthTenantMode: string
{
    case TenantScoped = 'tenant_scoped';
    case GlobalGateway = 'global_gateway';

    /**
     * Resolves an AuthTenantMode case from a raw configuration string.
     *
     * Execution Flow:
     * 1. Normalizes whitespace and casing on the input configuration string.
     * 2. Defaults empty strings to `self::TenantScoped` for zero-trust safety.
     * 3. Matches known enum backing values or throws `InvalidArgumentException` on invalid configuration values.
     *
     * @param string $value Raw environment configuration value.
     * @return self
     * @throws InvalidArgumentException When an unrecognized mode string is supplied.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return self::TenantScoped;
        }

        $mode = self::tryFrom($normalized);
        if ($mode === null) {
            throw new InvalidArgumentException(
                sprintf(
                    "Invalid AUTH_TENANT_MODE [%s]. Expected '%s' or '%s'.",
                    $value,
                    self::TenantScoped->value,
                    self::GlobalGateway->value
                )
            );
        }

        return $mode;
    }
}
