<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

/**
 * Title: Tenant Command Interface
 * Purpose:
 * - Defines the contract for all write operations (commands) related to Tenant entities.
 * - Handles creating, updating, and deleting tenants.
 * Why/Why this design:
 * - Enforces the CQRS (Command Query Responsibility Segregation) pattern by strictly separating write interfaces from read interfaces.
 * - Allows different implementations (e.g., event-sourced vs CRUD) without affecting the domain logic.
 * Teaching notes:
 * - Interface Segregation Principle (ISP) at work. Clients that only need to read tenants shouldn't depend on an interface that includes write methods.
 */
interface TenantCommandInterface extends \Magma\interfaces\cqrs\CommandInterface
{
    /**
     * Creates a new tenant entity in the storage system.
     *
     * @param \Magma\dto\TenantDTO $tenantDTO
     * @return bool
     */
    public function create(\Magma\dto\TenantDTO $tenantDTO): bool;

    /**
     * Updates an existing tenant entity.
     *
     * @param int $id
     * @param \Magma\dto\TenantDTO $tenantDTO
     * @return bool
     */
    public function update(int $id, \Magma\dto\TenantDTO $tenantDTO): bool;

    /**
     * Deletes a tenant entity by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
