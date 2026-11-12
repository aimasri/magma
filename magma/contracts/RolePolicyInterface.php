<?php

declare(strict_types=1);

namespace Magma\contracts;

/**
 * Title: Role Policy Contract
 *
 * Purpose:
 * - Defines the contract for resolving role-specific application policies including dashboard landing paths and session inactivity timeouts.
 * - Decouples core framework authentication and session management from hardcoded application role enums and domain assumptions.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Core framework components (`SessionTimeoutMiddleware`, `LoginController`, `PostLoginRedirectResolver`) depend on this abstraction rather than concrete role enums.
 * - Open/Closed Principle (OCP): Downstream applications can introduce arbitrary domain roles (e.g. 'founder', 'tenant_admin', 'auditor', 'support') and custom routing/timeout policies without modifying core framework code.
 * - Interface Segregation Principle (ISP): Keeps post-authentication routing and inactivity timeout contracts tightly focused without leaking unrelated authorization logic.
 *
 * Teaching notes:
 * - Frameworks must remain completely agnostic of downstream business domains. By abstracting role-based destination and timeout decisions behind `RolePolicyInterface`, Magma supports single-tenant, multi-tenant, and multi-tier enterprise role hierarchies seamlessly.
 */
interface RolePolicyInterface
{
    /**
     * Resolves the default post-authentication path-absolute destination URI for a given user role.
     *
     * Execution Flow:
     * 1. Inspects the provided role string identifier.
     * 2. Evaluates application-configured role-to-path mappings.
     * 3. Returns the appropriate path-absolute URI (e.g. '/admin', '/user', '/').
     *
     * Logic behind the logic:
     * - Accepting `?string $role` allows handling unassigned or guest states gracefully, ensuring a deterministic fallback URI is always returned.
     *
     * @param string|null $role The user's role identifier, or null if unassigned.
     * @return string The path-absolute landing URI.
     */
    public function resolveDashboardPath(?string $role): string;

    /**
     * Resolves the maximum allowed session inactivity duration in seconds for a given user role.
     *
     * Execution Flow:
     * 1. Inspects the provided role string identifier.
     * 2. Evaluates application-configured role-to-timeout mappings.
     * 3. Returns the maximum inactivity window in seconds.
     *
     * Logic behind the logic:
     * - Different roles carry varying risk profiles (e.g. administrative roles may require shorter inactivity windows under PCI-DSS or longer windows under privileged supervision), which must be configurable per deployment.
     *
     * @param string|null $role The user's role identifier, or null if unassigned.
     * @return int Inactivity timeout threshold in seconds.
     */
    public function resolveSessionTimeout(?string $role): int;
}
