<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use Magma\repositories\UserQueryRepository;
use Magma\security\AuthTenantMode;

/**
 * Title: UserQueryRepository Multi-Tenant Scoping Integration Test
 *
 * Purpose:
 * - Verifies real PostgreSQL SQL execution of `UserQueryRepository` under both `AuthTenantMode::TenantScoped` and `AuthTenantMode::GlobalGateway`.
 *
 * Why / Why this design:
 * - Executes against the live PostgreSQL connection inside `DatabaseIntegrationTestCase`'s rolled-back transaction using a session-scoped `TEMP TABLE users` so real query predicates (`AND (tenant_id = ? OR tenant_id IS NULL)`) and UTC timestamp parsing are deterministically proven without polluting persistent tables.
 *
 * Teaching notes:
 * - Always test both positive tenant matches and cross-tenant isolation boundaries when verifying multi-tenant repositories.
 */
final class UserQueryAuthTenantModeTest extends DatabaseIntegrationTestCase
{
    private int $tenantAUserId = 0;
    private int $tenantBUserId = 0;
    private int $globalAdminId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbManager->forceWriteForReads(true);
        $db = $this->dbManager->getWriteConnection();

        $db->exec("
            CREATE TEMP TABLE pg_temp.users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                role VARCHAR(64) NOT NULL DEFAULT 'user',
                password VARCHAR(255) NOT NULL,
                tenant_id INT NULL,
                password_changed_at TIMESTAMP NULL
            )
        ");

        $stmt = $db->prepare("
            INSERT INTO pg_temp.users (name, email, role, password, tenant_id, password_changed_at)
            VALUES (?, ?, ?, ?, ?, ?)
            RETURNING id
        ");

        $stmt->execute(['Alice Tenant 101', 'alice@tenant101.example', 'admin', 'hash_alice', 101, '2026-06-01 12:00:00']);
        $this->tenantAUserId = (int) $stmt->fetchColumn();

        $stmt->execute(['Bob Tenant 202', 'bob@tenant202.example', 'user', 'hash_bob', 202, '2026-06-02 15:30:00']);
        $this->tenantBUserId = (int) $stmt->fetchColumn();

        $stmt->execute(['Root Platform', 'root@platform.example', 'superadmin', 'hash_root', null, '2026-06-03 08:00:00']);
        $this->globalAdminId = (int) $stmt->fetchColumn();
    }

    /**
     * Verifies strict per-domain isolation in `AuthTenantMode::TenantScoped`.
     */
    public function testTenantScopedModeIsolatesAllLookupsByActiveTenant(): void
    {
        $repo = new UserQueryRepository(
            $this->dbManager,
            $this->tenantContext,
            AuthTenantMode::TenantScoped
        );

        $this->actAsTenant(101);

        // Tenant 101 user and global NULL superadmin are visible
        $alice = $repo->findByEmail('alice@tenant101.example');
        $this->assertNotNull($alice);
        $this->assertSame($this->tenantAUserId, $alice->getId());
        $this->assertSame(101, $alice->getTenantId());

        $root = $repo->findByEmail('root@platform.example');
        $this->assertNotNull($root);
        $this->assertSame($this->globalAdminId, $root->getId());
        $this->assertNull($root->getTenantId());

        // Tenant 202 user is strictly isolated across all 4 repository read methods
        $this->assertNull($repo->findByEmail('bob@tenant202.example'));
        $this->assertNull($repo->findForAuth('bob@tenant202.example'));
        $this->assertNull($repo->findById($this->tenantBUserId));
        $this->assertNull($repo->getPasswordChangedAt($this->tenantBUserId));

        // Same Tenant 101 user resolves via findForAuth, findById, and getPasswordChangedAt
        $authRow = $repo->findForAuth('alice@tenant101.example');
        $this->assertIsArray($authRow);
        $this->assertSame('hash_alice', $authRow['password']);
        $this->assertNotNull($repo->findById($this->tenantAUserId));
        $this->assertSame(
            (new \DateTimeImmutable('2026-06-01 12:00:00', new \DateTimeZone('UTC')))->getTimestamp(),
            $repo->getPasswordChangedAt($this->tenantAUserId)
        );
    }

    /**
     * Verifies central login gateway lookups and post-bind password timestamp checks in `AuthTenantMode::GlobalGateway`.
     */
    public function testGlobalGatewayModeAllowsCentralAuthWhileScopingPasswordChangedAt(): void
    {
        $repo = new UserQueryRepository(
            $this->dbManager,
            $this->tenantContext,
            AuthTenantMode::GlobalGateway
        );

        // Simulate unauthenticated landing gateway host resolved to default tenant 101
        $this->actAsTenant(101);

        // Tenant 202 user can authenticate, request password reset, and rotate persistent tokens at central gateway
        $bobByEmail = $repo->findByEmail('bob@tenant202.example');
        $this->assertNotNull($bobByEmail);
        $this->assertSame(202, $bobByEmail->getTenantId());

        $bobAuth = $repo->findForAuth('bob@tenant202.example');
        $this->assertIsArray($bobAuth);
        $this->assertSame('hash_bob', $bobAuth['password']);

        $bobById = $repo->findById($this->tenantBUserId);
        $this->assertNotNull($bobById);
        $this->assertSame(202, $bobById->getTenantId());

        // Before TenantSecurityMiddleware rebinds TenantContext from 101 -> 202, getPasswordChangedAt remains tenant-guarded
        $this->assertNull($repo->getPasswordChangedAt($this->tenantBUserId));

        // Once TenantSecurityMiddleware rebinds TenantContext to authenticated user's tenant 202, getPasswordChangedAt resolves
        $this->actAsTenant(202);
        $this->assertSame(
            (new \DateTimeImmutable('2026-06-02 15:30:00', new \DateTimeZone('UTC')))->getTimestamp(),
            $repo->getPasswordChangedAt($this->tenantBUserId)
        );
    }
}
