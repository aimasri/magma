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

class InventoryServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Repositories
        $container->set(InventoryLedgerRepositoryInterface::class, function ($c) {
            return new InventoryLedgerRepository($c->get('db.write'), $c->get('db.read'));
        });

        $container->set(VendorInventoryRepositoryInterface::class, function ($c) {
            return new VendorInventoryRepository($c->get('db.write'), $c->get('db.read'));
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
