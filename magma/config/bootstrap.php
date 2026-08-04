<?php

/**
 * Application Bootstrap — Service Configuration
 *
 * Purpose:
 * - Bootstraps the autoloader, initializes the environment, and configures 
 *   the Dependency Injection Container using Service Providers.
 *
 * Why / Why this design:
 * - Decoupling the setup phase into a bootstrap script ensures that the entry points 
 *   (like `public/index.php` for web or an artisan-like CLI script) remain incredibly thin. 
 *   This is the Front Controller pattern in action.
 *
 * Teaching notes:
 * - The Service Provider pattern breaks up monolithic DI configurations into 
 *   modular chunks, making the application much easier to scale.
 */

require_once __DIR__ . '/autoload.php';

use Magma\container\Container;
use Magma\config\Config;
use Magma\providers\CoreServiceProvider;
use Magma\providers\DatabaseServiceProvider;
use Magma\providers\InfrastructureServiceProvider;
use Magma\providers\RoutingServiceProvider;
use Magma\providers\RepositoryServiceProvider;
use Magma\providers\DomainServiceProvider;
use Magma\providers\HttpServiceProvider;

// Load environment variables and system settings.
Config::initialize();

/**
 * Initialize the Service Container.
 */
$container = new Container();

$container->set(Container::class, function () use ($container) {
    // Registering the container itself allows services to resolve 
    // other dependencies dynamically if needed.
    return $container;
});

/**
 * Register Service Providers
 */
$providers = [
    new CoreServiceProvider(),
    new InfrastructureServiceProvider(),
    new DatabaseServiceProvider(),
    new RoutingServiceProvider(),
    new RepositoryServiceProvider(),
    new DomainServiceProvider(),
    new HttpServiceProvider(),
    new \Magma\providers\EventServiceProvider(),
    // Uncomment to enable the Inventory module
    // new \Magma\modules\Inventory\providers\InventoryServiceProvider(),
];

foreach ($providers as $provider) {
    $provider->register($container);
}
