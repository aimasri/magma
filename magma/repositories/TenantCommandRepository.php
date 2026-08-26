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
        $bindings = [
            'name' => $tenantDTO->name,
            'tagline' => $tenantDTO->tagline,
            'email' => $tenantDTO->email,
            'plan_id' => $tenantDTO->plan_id,
            'subscription_status' => $tenantDTO->subscription_status,
            'billing_cycle_anchor' => $tenantDTO->billing_cycle_anchor,
            'payment_gateway_customer_id' => $tenantDTO->payment_gateway_customer_id,
            'theme_settings' => json_encode($tenantDTO->theme_settings),
        ];

        $fields = array_keys($bindings);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = sprintf(
            "INSERT INTO tenants (%s) VALUES (%s)",
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function update(int $id, \Magma\dto\TenantDTO $tenantDTO): bool
    {
        $bindings = [
            'name' => $tenantDTO->name,
            'tagline' => $tenantDTO->tagline,
            'email' => $tenantDTO->email,
            'plan_id' => $tenantDTO->plan_id,
            'subscription_status' => $tenantDTO->subscription_status,
            'billing_cycle_anchor' => $tenantDTO->billing_cycle_anchor,
            'payment_gateway_customer_id' => $tenantDTO->payment_gateway_customer_id,
            'theme_settings' => json_encode($tenantDTO->theme_settings),
        ];

        $setClauses = [];
        foreach (array_keys($bindings) as $column) {
            $setClauses[] = "$column = :$column";
        }

        $bindings['id'] = $id;

        $sql = sprintf(
            "UPDATE tenants SET %s WHERE id = :id",
            implode(', ', $setClauses)
        );

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->getDb()->prepare("DELETE FROM tenants WHERE id = :id");
        return $stmt->execute([
            'id' => $id
        ]);
    }
}
