<?php

declare(strict_types=1);

namespace App\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;

/**
 * Title: Application Service Provider
 *
 * Purpose:
 * - Bootstraps application-specific dependencies and bindings into the container.
 *
 * Why / Why this design:
 * - Separates application-level configuration from the core framework providers.
 *
 * Teaching notes:
 * - Register any bindings required by application services here.
 */
class AppServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(SystemInfoProviderInterface::class, function () {
            return new SystemInfoProvider();
        });

        $container->bind(
            \App\services\SystemDiagnosticsServiceInterface::class,
            \App\services\SystemDiagnosticsService::class
        );
    }
}
