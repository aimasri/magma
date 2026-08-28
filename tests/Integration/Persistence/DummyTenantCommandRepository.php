<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use Magma\models\AbstractCommandRepository;

class DummyTenantCommandRepository extends AbstractCommandRepository
{
    /**
     * Inserts a resource manually (without relying on PostgreSqlInsertBuilder for this basic test)
     * but still explicitly bound to the tenant context.
     */
    public function createResource(string $name): int
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            INSERT INTO magma_test_resources (tenant_id, name) 
            VALUES (:tenant_id, :name) 
            RETURNING id
        ");
        
        $stmt->execute(['name' => $name, 'tenant_id' => $this->tenantContext->getTenantId()]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Attempts to update a resource. 
     * IMPORTANT: Downstream repos MUST append `AND tenant_id = :tenant_id` to prevent cross-tenant mutations.
     */
    public function updateResourceName(int $resourceId, string $newName): int
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            UPDATE magma_test_resources 
            SET name = :name 
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        
        $stmt->execute([
            'name' => $newName, 
            'id' => $resourceId, 
            'tenant_id' => $this->tenantContext->getTenantId()
        ]);
        
        return $stmt->rowCount();
    }

    /**
     * Attempts to delete a resource.
     */
    public function deleteResource(int $resourceId): int
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            DELETE FROM magma_test_resources 
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        
        $stmt->execute([
            'id' => $resourceId,
            'tenant_id' => $this->tenantContext->getTenantId()
        ]);
        
        return $stmt->rowCount();
    }
}
