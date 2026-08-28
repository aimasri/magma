<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use Magma\models\AbstractQueryRepository;

class DummyTenantQueryRepository extends AbstractQueryRepository
{
    private function requireTenantId(): int
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null) {
            throw new \RuntimeException('Fatal Architecture Exception: Cannot execute tenant-scoped query without a resolved TenantContext.');
        }
        return $tenantId;
    }

    /**
     * Executes a basic SELECT query to test simple WHERE tenant_id scoping.
     */
    public function findResourceByName(string $name): ?array
    {
        $tenantId = $this->requireTenantId();
        
        $db = $this->getDb();
        $stmt = $db->prepare("SELECT * FROM magma_test_resources WHERE name = :name AND tenant_id = :tenant_id");
        $stmt->execute(['name' => $name, 'tenant_id' => $tenantId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Executes a complex JOIN query to ensure boundaries apply across tables.
     */
    public function getResourceWithChildren(int $resourceId): array
    {
        $tenantId = $this->requireTenantId();
        
        $db = $this->getDb();
        $stmt = $db->prepare("
            SELECT r.id, r.name, c.details
            FROM magma_test_resources r
            LEFT JOIN magma_test_resource_children c 
                ON c.parent_id = r.id AND c.tenant_id = :tenant_id
            WHERE r.id = :id AND r.tenant_id = :tenant_id
        ");
        
        $stmt->execute(['id' => $resourceId, 'tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }
}
