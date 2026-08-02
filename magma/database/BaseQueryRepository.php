<?php

namespace Magma\database;

use Magma\security\TenantContext;

/**
 * Title: Base Query Repository (CQRS Read Model)
 *
 * Purpose:
 * - Base class for all read-model CQRS repositories (and Analytics).
 * - Forces the use of the Read database connection.
 *
 * Why this design:
 * - Command Query Responsibility Segregation (CQRS): Physically separates read concerns from write concerns.
 * - Database Replication: Directs read-heavy traffic to database replicas, allowing for horizontal read scaling without exhausting the primary write connection.
 * - Tenant Isolation: Automatically provides the TenantContext to enforce strict multi-tenant filtering on reads.
 *
 * Teaching notes:
 * - If you are writing an UPDATE, INSERT, or DELETE, you should NOT be using or extending this class. Extend BaseCommandRepository instead.
 */
abstract class BaseQueryRepository
{
    protected \PDO $db;
    protected TenantContext $tenantContext;

    public function __construct(DatabaseConnectionManager $dbManager, TenantContext $tenantContext)
    {
        $this->db = $dbManager->getReadConnection();
        $this->tenantContext = $tenantContext;
    }
}
