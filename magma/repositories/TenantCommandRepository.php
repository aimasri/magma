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
    public function __construct(
        DatabaseConnectionManager $dbManager,
        TenantContext $tenantContext
    ) {
        parent::__construct($dbManager, $tenantContext);
    }



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

    public function delete(int $id): bool
    {
        $affected = $this->executeDelete('tenants', '"id" = :id', [':id' => $id]);
        return $affected > 0;
    }
}
