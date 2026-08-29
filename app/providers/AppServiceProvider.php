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
    /**
     * Registers application-specific services and dependencies into the DI container.
     *
     * Execution Flow:
     * 1. Binds the SystemInfoProviderInterface to its concrete implementation.
     * 2. Binds the SystemDiagnosticsServiceInterface to SystemDiagnosticsService.
     *
     * Logic behind the logic:
     * - Centralizing these bindings ensures that the container is fully aware of application-specific
     *   contracts, promoting the Dependency Inversion Principle and making swapping implementations trivial.
     *
     * @param Container $container The dependency injection container.
     * @return void
     */
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
