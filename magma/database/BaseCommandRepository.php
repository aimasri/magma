<?php

declare(strict_types=1);

namespace Magma\database;

use PDO;
use Magma\security\TenantContext;

/**
 * Title: Base Command Repository (CQRS Write Model)
 *
 * Purpose:
 * - Base class for all write-model CQRS repositories.
 * - Forces the use of the Write database connection.
 *
 * Why this design:
 * - Command Query Responsibility Segregation (CQRS): Distinguishes state-mutating operations from pure reads.
 * - Primary Node Routing: Ensures that state-mutating operations never accidentally hit a read replica (which would cause lag anomalies or read-only errors).
 * - Tenant Isolation: Automatically provides the TenantContext to enforce multi-tenant isolation during mutations.
 *
 * Teaching notes:
 * - All queries issued by classes inheriting this base should be INSERT, UPDATE, or DELETE operations.
 * - Complex reads should be offloaded to classes extending BaseQueryRepository or AbstractQueryRepository.
 */
abstract class BaseCommandRepository
{
    protected DatabaseConnectionManager $dbManager;
    protected TenantContext $tenantContext;

    public function __construct(DatabaseConnectionManager $dbManager, TenantContext $tenantContext)
    {
        $this->dbManager = $dbManager;
        $this->tenantContext = $tenantContext;
    }

    protected function getDb(): PDO
    {
        return $this->dbManager->getWriteConnection();
    }
}
