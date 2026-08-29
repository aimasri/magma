<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\TenantCommandInterface;

use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Magma\models\AbstractCommandRepository;

/**
 * Title: Tenant Command Repository
 * Purpose:
 * - Implements the actual database write operations for Tenant entities.
 * - Handles the creation, updating, and deletion of tenant records.
 * - Coordinates with the TenantMapper to translate domain data into database-ready formats.
 * Why/Why this design:
 * - Follows the Repository pattern focused strictly on Commands (CQRS), ensuring write operations are isolated.
 * - Utilizes a Mapper to keep the repository unaware of complex object structures, maintaining a single responsibility (database interaction).
 * Teaching notes:
 * - This class directly manipulates the write database connection. It should not contain business logic, only data mapping and persistence execution.
 */
class TenantCommandRepository extends AbstractCommandRepository implements TenantCommandInterface
{
    /**
     * Initializes the TenantCommandRepository.
     *
     * Execution Flow:
     * 1. Calls the parent constructor with the database manager and tenant context to establish the write connection.
     *
     * Logic behind the logic:
     * - Injects dependencies required to manage the connection to the primary database used for writes, adhering to Dependency Inversion.
     */
    public function __construct(
        DatabaseConnectionManager $dbManager,
        TenantContext $tenantContext
    ) {
        parent::__construct($dbManager, $tenantContext);
    }



    /**
     * Persists a new tenant record into the database.
     *
     * Execution Flow:
     * 1. Prepares an array of fields from the TenantDTO, encoding the theme_settings to JSON.
     * 2. Calls the abstract repository's insertAndGetId method targeting the 'tenants' table.
     * 3. Returns a boolean indicating success if a valid ID is generated.
     *
     * Logic behind the logic:
     * - Data formatting like JSON encoding is isolated here to keep domain models pure, following the Repository pattern's goal of abstracting data access.
     */
    public function create(\Magma\dto\TenantDTO $tenantDTO): bool
    {
        $id = $this->insertAndGetId('tenants', [
            'name' => $tenantDTO->name,
            'tagline' => $tenantDTO->tagline,
            'email' => $tenantDTO->email,
            'plan_id' => $tenantDTO->plan_id,
            'subscription_status' => $tenantDTO->subscription_status,
            'billing_cycle_anchor' => $tenantDTO->billing_cycle_anchor,
            'payment_gateway_customer_id' => $tenantDTO->payment_gateway_customer_id,
            'theme_settings' => json_encode($tenantDTO->theme_settings),
        ]);
        return $id > 0;
    }

    /**
     * Updates an existing tenant record in the database.
     *
     * Execution Flow:
     * 1. Maps the TenantDTO fields to an associative array, serializing theme_settings as JSON.
     * 2. Invokes executeUpdate on the 'tenants' table using the provided ID constraint.
     * 3. Returns true if one or more rows were successfully affected.
     *
     * Logic behind the logic:
     * - Reusing the AbstractCommandRepository's parameter binding prevents SQL injection and abstracts the raw SQL execution from this class.
     */
    public function update(int $id, \Magma\dto\TenantDTO $tenantDTO): bool
    {
        $affected = $this->executeUpdate('tenants', [
            'name' => $tenantDTO->name,
            'tagline' => $tenantDTO->tagline,
            'email' => $tenantDTO->email,
            'plan_id' => $tenantDTO->plan_id,
            'subscription_status' => $tenantDTO->subscription_status,
            'billing_cycle_anchor' => $tenantDTO->billing_cycle_anchor,
            'payment_gateway_customer_id' => $tenantDTO->payment_gateway_customer_id,
            'theme_settings' => json_encode($tenantDTO->theme_settings),
        ], '"id" = :id', [':id' => $id]);
        return $affected > 0;
    }

    /**
     * Deletes a tenant record from the database.
     *
     * Execution Flow:
     * 1. Executes a delete statement on the 'tenants' table for the specific ID constraint.
     * 2. Returns true if the deletion affected at least one row.
     *
     * Logic behind the logic:
     * - Provides a clean API for physical deletion. If soft deletion is later required, only this implementation changes, avoiding impacts on the domain layer.
     */
    public function delete(int $id): bool
    {
        $affected = $this->executeDelete('tenants', '"id" = :id', [':id' => $id]);
        return $affected > 0;
    }
}
