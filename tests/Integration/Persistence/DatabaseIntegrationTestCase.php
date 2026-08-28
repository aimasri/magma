<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use PHPUnit\Framework\TestCase;
use Magma\container\Container;
use Magma\config\Config;
use Magma\database\DatabaseConnectionManager;
use Magma\database\DatabaseTransactionManager;
use Magma\security\TenantContext;

/**
 * Title: Database Integration Test Case
 *
 * Purpose:
 * - Provides a fully wired DI container and real PostgreSQL database connection for tests.
 * - Enforces zero data pollution by wrapping every test in an automatic transaction rollback.
 * - Serves as the industry-agnostic base test class for all Magma framework persistence tests 
 *   and downstream application module repository tests.
 *
 * Why / Why this design:
 * - Real Engine Testing: Proves that Magma's PostgreSQL-specific syntax executes successfully.
 * - Transactional Boundaries: The BEGIN/ROLLBACK cycle guarantees the test database is never permanently polluted.
 *
 * Teaching notes:
 * - Downstream projects should extend this class to inherit a safe database testing environment.
 */
abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected Container $container;
    protected DatabaseTransactionManager $transactionManager;
    protected DatabaseConnectionManager $dbManager;
    protected TenantContext $tenantContext;
    protected \Magma\infrastructure\time\MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialize environment
        $envPath = defined('ROOT_DIR') ? ROOT_DIR . '/.env' : __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            Config::initialize($envPath);
        }

        // 2. Boot a fresh container
        $this->container = $this->createApplicationContainer();

        // 3. Resolve the DB manager and start the global test transaction
        $this->dbManager = $this->container->get(DatabaseConnectionManager::class);
        $this->transactionManager = new DatabaseTransactionManager($this->dbManager);
        
        $this->transactionManager->begin();

        // 4. Create a mock tenant context with a default test tenant
        $this->tenantContext = new TenantContext();
        $this->tenantContext->setTenantId(999);
        $this->container->set(TenantContext::class, function () {
            return $this->tenantContext;
        });

        // Setup MockClock
        $this->clock = new \Magma\infrastructure\time\MockClock();
        $this->container->set(\Magma\contracts\ClockInterface::class, function () {
            return $this->clock;
        });

        // 5. Scaffold temporary schema purely for framework tests (auto-drops on session end)
        $this->setupTemporaryTestSchema();
    }

    protected function travelTo(string $datetime): void
    {
        $this->clock->setTime(new \DateTimeImmutable($datetime));
    }

    protected function tearDown(): void
    {
        // 1. Automatically roll back all database changes made during the test.
        if ($this->transactionManager->inTransaction()) {
            $this->transactionManager->rollBack();
        }

        // 2. Disconnect to prevent connection pool exhaustion across a large test suite
        $this->dbManager->disconnect();

        parent::tearDown();
    }

    /**
     * Set a different tenant ID dynamically during a test.
     */
    protected function actAsTenant(int $tenantId): void
    {
        $this->tenantContext->setTenantId($tenantId);
    }

    /**
     * Builds the Magma DI container.
     */
    private function createApplicationContainer(): Container
    {
        $container = new Container();

        $container->set(DatabaseConnectionManager::class, function () {
            $settings = Config::getDatabaseSettings();
            return new DatabaseConnectionManager(
                $settings,
                $settings, // Use primary for read in tests
                false
            );
        });

        $container->set('db.write', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getWriteConnection();
        });

        $container->set('db.read', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getReadConnection();
        });

        return $container;
    }

    /**
     * Scaffolds temporary tables specific to the Magma framework's agnostic tests.
     */
    private function setupTemporaryTestSchema(): void
    {
        $db = $this->dbManager->getWriteConnection();

        // TEMP tables live only for the duration of the PDO session.
        $db->exec("
            CREATE TEMP TABLE IF NOT EXISTS magma_test_tenants (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                domain VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TEMP TABLE IF NOT EXISTS magma_test_resources (
                id SERIAL PRIMARY KEY,
                tenant_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TEMP TABLE IF NOT EXISTS magma_test_resource_children (
                id SERIAL PRIMARY KEY,
                tenant_id INT NOT NULL,
                parent_id INT NOT NULL,
                details VARCHAR(255) NOT NULL,
                FOREIGN KEY (parent_id) REFERENCES magma_test_resources(id) ON DELETE CASCADE
            );
        ");
    }

    protected function tenantFactory(): \Tests\Integration\Persistence\Factories\GenericTenantFactory
    {
        return new \Tests\Integration\Persistence\Factories\GenericTenantFactory($this->dbManager);
    }
}
