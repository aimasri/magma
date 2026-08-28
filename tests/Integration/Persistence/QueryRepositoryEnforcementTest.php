<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

class QueryRepositoryEnforcementTest extends DatabaseIntegrationTestCase
{
    private DummyTenantCommandRepository $commandRepo;
    private DummyTenantQueryRepository $queryRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandRepo = new DummyTenantCommandRepository($this->dbManager, $this->tenantContext);
        $this->queryRepo = new DummyTenantQueryRepository($this->dbManager, $this->tenantContext);
    }

    public function test_querying_other_tenant_returns_empty_dataset(): void
    {
        // 1. Seed data for Tenant A (ID: 10)
        $this->actAsTenant(10);
        $this->commandRepo->createResource('Tenant A Secret Data');

        // 2. Seed data for Tenant B (ID: 20)
        $this->actAsTenant(20);
        $this->commandRepo->createResource('Tenant B Secret Data');

        // 3. Act as Tenant A and attempt to query Tenant B's data
        $this->actAsTenant(10);
        
        // Because DummyTenantQueryRepository currently doesn't enforce tenant_id in findResourceByName,
        // this test would actually fail if we don't modify DummyTenantQueryRepository to use the MultiTenantKeysetQueryBuilder
        // or explicitly append the WHERE clause. 
        // We will simulate the framework's enforcement by ensuring our queries respect the context.
        // For the sake of this scaffolding, we assume the repository was written securely.
        
        // Wait, DummyTenantQueryRepository::findResourceByName in our scaffold doesn't have `AND tenant_id = :tenant_id`.
        // Let's assume a secure repository *would* have it. (We'll update DummyTenantQueryRepository).
        $result = $this->queryRepo->findResourceByName('Tenant B Secret Data');
        
        $this->assertNull($result, "Data leak! Tenant A was able to read Tenant B's data.");
    }

    public function test_complex_query_scoping_maintains_boundary_on_joins(): void
    {
        // 1. Seed parent data for Tenant A
        $this->actAsTenant(10);
        $resourceId = $this->commandRepo->createResource('Parent Record');

        // 2. Insert child records directly via PDO to bypass repo limits for seeding malicious state
        $db = $this->dbManager->getWriteConnection();
        // Insert a valid child for Tenant A
        $db->exec("INSERT INTO magma_test_resource_children (tenant_id, parent_id, details) VALUES (10, {$resourceId}, 'Valid Child')");
        // Insert a malicious child record pretending to belong to the same parent but assigned to Tenant B
        $db->exec("INSERT INTO magma_test_resource_children (tenant_id, parent_id, details) VALUES (20, {$resourceId}, 'Malicious Child Leak')");

        // 3. Act as Tenant A and query the parent with children
        $this->actAsTenant(10);
        $results = $this->queryRepo->getResourceWithChildren($resourceId);

        // 4. Assert that ONLY the child record belonging to Tenant A was joined, 
        // proving the AND c.tenant_id = :tenant_id boundary condition worked.
        $this->assertCount(1, $results);
        $this->assertSame('Valid Child', $results[0]['details']);
    }
}
