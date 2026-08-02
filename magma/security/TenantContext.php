<?php

namespace Magma\security;

/**
 * Title: Multi-Tenant Context
 * 
 * Purpose:
 * - Securely holds the multi-tenancy context (vendor ID) for the current request lifecycle.
 * 
 * Why this design:
 * - Security/Isolation: Centralizes the tenant ID to guarantee that repositories and services always filter queries by the correct tenant without relying on controller logic.
 * - Dependency Injection: Can be injected anywhere in the application, promoting a robust, secure, and easily testable multi-tenant architecture.
 * 
 * Teaching notes:
 * - This class should only be populated once per request, typically via middleware (e.g., `TenantMiddleware`) that extracts it from the authenticated session.
 */
class TenantContext
{
    private ?int $vendorId = null;

    public function setVendorId(int $vendorId): void
    {
        $this->vendorId = $vendorId;
    }

    public function getVendorId(): int
    {
        if ($this->vendorId === null) {
            throw new \RuntimeException('Vendor ID is not set in the current tenant context. This usually indicates a middleware failure or missing authentication.');
        }
        return $this->vendorId;
    }

    public function hasVendorId(): bool
    {
        return $this->vendorId !== null;
    }
}
