<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\http\RequestInterface;

/**
 * Title: Multi-Tenant Security & Query Scoping Context
 *
 * Purpose:
 * - Securely maintains the active multi-tenant boundary (`tenant_id` / `vendor_id` and optional `venue_id`) for the current execution lifecycle.
 * - Coordinates with `TenantContextProviderInterface` for pluggable tenant resolution.
 * - Injected into CQRS repositories, query builders, and domain services to guarantee strict tenant isolation in SQL queries.
 *
 * Why / Why this design:
 * - Zero-Trust Data Boundary: Centralizing tenant identification prevents developers from relying on manual controller inputs or missing `WHERE tenant_id = ?` query clauses.
 * - Backward Compatibility Facade: Supports both modern `getTenantId()` and legacy `getVendorId()` calls seamlessly.
 *
 * Teaching notes:
 * - This singleton context is initialized once per HTTP request via `TenantSecurityMiddleware` or `TenantContextMiddleware`.
 */
class TenantContext
{
    private ?int $tenantId = null;
    private ?int $venueId = null;
    private ?TenantContextProviderInterface $provider;

    public function __construct(?TenantContextProviderInterface $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * Sets the active tenant ID for the current request context.
     *
     * @param int $tenantId
     * @return void
     */
    public function setTenantId(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Retrieves the active tenant ID, throwing an exception if uninitialized.
     *
     * @return int
     * @throws \RuntimeException
     */
    public function getTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new \RuntimeException(
                'Tenant ID is not set in the current tenant context. This usually indicates unauthenticated access, missing tenant scoping middleware, or a cross-tenant boundary breach.'
            );
        }
        return $this->tenantId;
    }

    /**
     * Determines whether a tenant ID has been bound to the active context.
     *
     * @return bool
     */
    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Alias for setTenantId() to maintain backward compatibility.
     *
     * @param int $vendorId
     * @return void
     */
    public function setVendorId(int $vendorId): void
    {
        $this->setTenantId($vendorId);
    }

    /**
     * Alias for getTenantId() to maintain backward compatibility.
     *
     * @return int
     */
    public function getVendorId(): int
    {
        return $this->getTenantId();
    }

    /**
     * Alias for hasTenantId() to maintain backward compatibility.
     *
     * @return bool
     */
    public function hasVendorId(): bool
    {
        return $this->hasTenantId();
    }

    /**
     * Sets the optional venue or sub-organization identifier.
     *
     * @param int|null $venueId
     * @return void
     */
    public function setVenueId(?int $venueId): void
    {
        $this->venueId = $venueId;
    }

    /**
     * Retrieves the optional venue identifier.
     *
     * @return int|null
     */
    public function getVenueId(): ?int
    {
        return $this->venueId;
    }

    /**
     * Determines whether a venue ID is bound to the context.
     *
     * @return bool
     */
    public function hasVenueId(): bool
    {
        return $this->venueId !== null;
    }

    /**
     * Resolves tenant information dynamically using the registered provider.
     *
     * Execution Flow:
     * 1. If provider is registered, delegates `resolveTenantId($request)`.
     * 2. If a tenant ID is returned, sets `$this->tenantId`.
     * 3. Calls `resolveVenueId($request)` and sets `$this->venueId` if present.
     *
     * @param RequestInterface $request
     * @return int|null Resolved tenant ID
     */
    public function resolveFromRequest(RequestInterface $request): ?int
    {
        if ($this->provider !== null) {
            $tenantId = $this->provider->resolveTenantId($request);
            if ($tenantId !== null) {
                $this->setTenantId($tenantId);
            }

            $venueId = $this->provider->resolveVenueId($request);
            if ($venueId !== null) {
                $this->setVenueId($venueId);
            }

            return $tenantId;
        }

        return null;
    }

    /**
     * Resets the context state (useful for background workers and testing teardown).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->tenantId = null;
        $this->venueId = null;
    }
}
