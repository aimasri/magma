<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use RuntimeException;

class MissingContextPreventionTest extends DatabaseIntegrationTestCase
{
    public function test_executing_query_without_context_throws_fatal_architecture_exception(): void
    {
        // 1. Instantiate the repository without injecting the TenantContext
        $queryRepo = new DummyTenantQueryRepository($this->dbManager, null);

        // 2. Expect a fatal architecture exception to prevent global scoping leaks
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fatal Architecture Exception: Cannot execute tenant-scoped query without a resolved TenantContext.');

        // 3. Attempt to execute the query
        $queryRepo->findResourceByName('Global Attack');
    }
}
