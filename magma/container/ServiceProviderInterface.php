<?php

namespace Magma\container;

use Magma\container\Container;

/**
 * Title: Service Provider Contract
 *
 * Purpose:
 * - Defines a standard way to register dependencies into the application's Container.
 * - Allows the bootstrap process to iterate over an array of providers safely.
 *
 * Why / Why this design:
 * - The Service Provider pattern separates the configuration of the DI Container
 *   into logical, cohesive modules (like Core components vs. App components) 
 *   rather than a single monolithic bootstrap file.
 *
 * Teaching notes:
 * - This interface only requires a `register` method. Advanced frameworks (like Laravel) 
 *   also include a `boot` method for actions that require all other services to be 
 *   registered first before they can execute.
 */
interface ServiceProviderInterface
{
    /**
     * Registers services and parameters into the provided container.
     * 
     * Logic behind the logic:
     * - Provides a clean, isolated scope for setting up dependencies before the application starts handling requests, ensuring the container is fully primed.
     * 
     * @param Container $container The dependency injection container.
     */
    public function register(Container $container): void;
}
