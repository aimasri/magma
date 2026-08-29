<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\http\RequestInterface;

/**
 * Title: Multi-Tenant Context Provider Contract
 *
 * Purpose:
 * - Defines the contract for resolving the active tenant and optional venue identity from incoming HTTP requests.
 * - Allows domain applications to plug in custom tenant resolution strategies (subdomain, header, route slug, authenticated user session, JWT claim, API key).
 *
 * Why / Why this design:
 * - Strategy Pattern & Dependency Inversion: Core framework persistence layers and middleware depend on this abstraction rather than hardcoding tenant session logic. Different applications (e.g. B2B SaaS with custom subdomains vs Multi-tenant API with headers) can plug in custom resolvers seamlessly.
 *
 * Teaching notes:
 * - Returning null indicates no tenant could be resolved from the request, allowing middleware to reject or pass public routes.
 */
interface TenantContextProviderInterface
{
    /**
     * Resolves the primary tenant/vendor ID from the request.
     *
     * @param RequestInterface $request
     * @return int|null
     */
    public function resolveTenantId(RequestInterface $request): ?int;

    /**
     * Resolves an optional sub-venue or location ID from the request.
     *
     * @param RequestInterface $request
     * @return int|null
     */
    public function resolveVenueId(RequestInterface $request): ?int;

    /**
     * Resolves the primary domain for a given tenant ID.
     *
     * @param int $tenantId
     * @return string|null
     */
    public function resolveDomainByTenantId(int $tenantId): ?string;
}
