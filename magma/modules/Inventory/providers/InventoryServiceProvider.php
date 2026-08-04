<?php

namespace Magma\modules\Inventory\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\queue\QueueInterface;
use Magma\modules\Inventory\models\InventoryLedgerRepository;
use Magma\modules\Inventory\models\InventoryLedgerRepositoryInterface;
use Magma\modules\Inventory\models\VendorInventoryRepository;
use Magma\modules\Inventory\models\VendorInventoryRepositoryInterface;
use Magma\modules\Inventory\services\InventoryService;

/**
 * Title: Inventory Service Provider
 *
 * Purpose:
 * - Bootstraps the Inventory module into the global Dependency Injection container.
 * - Wires interfaces to their concrete Repository and Service implementations.
 *
 * Why / Why this design:
 * - Enforces the Dependency Inversion principle by centralizing object construction.
 * - Keeps controllers and services ignorant of how their dependencies are instantiated or configured.
 *
 * Teaching notes:
 * - This acts as a composition root for the Inventory domain boundary.
 * - When writing tests, this provider can be easily swapped with a mock provider to inject test doubles.
 */
class InventoryServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers all bindings for the Inventory module.
     *
     * 1. Binds the InventoryLedgerRepositoryInterface to its concrete class, injecting read/write DB connections.
     * 2. Binds the VendorInventoryRepositoryInterface to its concrete class with similar dependencies.
     * 3. Registers the InventoryService, wiring it with the ledger repository and a queue interface for asynchronous processing.
     *
     * Logic behind the logic:
     * - Supplying distinct read and write database connections supports a CQRS-like architecture,
     *   allowing read queries to hit replicas while writes go to the primary database instance.
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        // Repositories
        $container->set(InventoryLedgerRepositoryInterface::class, function ($c) {
            return new InventoryLedgerRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        $container->set(VendorInventoryRepositoryInterface::class, function ($c) {
            return new VendorInventoryRepository(
                $c->get(\Magma\database\DatabaseConnectionManager::class),
                $c->get(\Magma\security\TenantContext::class)
            );
        });

        // Services
        $container->set(InventoryService::class, function ($c) {
            return new InventoryService(
                $c->get(InventoryLedgerRepositoryInterface::class),
                $c->get(QueueInterface::class)
            );
        });
    }
}
