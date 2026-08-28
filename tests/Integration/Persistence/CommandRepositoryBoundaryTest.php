<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

class CommandRepositoryBoundaryTest extends DatabaseIntegrationTestCase
{
    private DummyTenantCommandRepository $commandRepo;
    private DummyTenantQueryRepository $queryRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandRepo = new DummyTenantCommandRepository($this->dbManager, $this->tenantContext);
        $this->queryRepo = new DummyTenantQueryRepository($this->dbManager, $this->tenantContext);
    }

    public function test_update_command_silently_fails_on_other_tenant_record(): void
    {
        // 1. Seed data for Tenant A (ID: 10)
        $this->actAsTenant(10);
        $resourceId = $this->commandRepo->createResource('Original Name');

        // 2. Act as Tenant B (ID: 20) and attempt to maliciously update Tenant A's record
        $this->actAsTenant(20);
        $rowsAffected = $this->commandRepo->updateResourceName($resourceId, 'Hacked Name');

        // 3. Assert zero rows were updated because of the `AND tenant_id = :tenant_id` boundary
        $this->assertSame(0, $rowsAffected, "Data leak! Tenant B successfully mutated Tenant A's record.");

        // 4. Verify the record is unchanged by checking as Tenant A
        $this->actAsTenant(10);
        $resource = $this->queryRepo->findResourceByName('Original Name');
        $this->assertNotNull($resource);
        $this->assertSame('Original Name', $resource['name']);
    }

    public function test_delete_command_silently_fails_on_other_tenant_record(): void
    {
        // 1. Seed data for Tenant A
        $this->actAsTenant(10);
        $resourceId = $this->commandRepo->createResource('To Be Deleted');

        // 2. Act as Tenant B and attempt deletion
        $this->actAsTenant(20);
        $rowsAffected = $this->commandRepo->deleteResource($resourceId);

        // 3. Assert zero rows were deleted
        $this->assertSame(0, $rowsAffected);

        // 4. Verify the record still exists for Tenant A
        $this->actAsTenant(10);
        $resource = $this->queryRepo->findResourceByName('To Be Deleted');
        $this->assertNotNull($resource);
    }
}
