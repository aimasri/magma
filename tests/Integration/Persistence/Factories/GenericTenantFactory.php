<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence\Factories;

class GenericTenantFactory extends AbstractModelFactory
{
    protected string $tableName = 'magma_test_tenants';

    protected function getDefaults(): array
    {
        return [
            'name' => 'Acme Corp',
            'domain' => 'acme.local',
        ];
    }
}
