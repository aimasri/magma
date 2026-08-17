<?php

declare(strict_types=1);

namespace App\providers;

/**
 * Title: System Info Provider Interface
 *
 * Purpose:
 * - Abstract system-level calls (memory, version, OS) to allow mocking.
 *
 * Why / Why this design:
 * - Follows Dependency Inversion Principle. We don't want services calling global functions.
 *
 * Teaching notes:
 * - Useful for testing system diagnostics logic.
 */
interface SystemInfoProviderInterface
{
    /**
     * Retrieves the peak memory usage of the PHP script in bytes.
     *
     * @return int Memory peak usage in bytes.
     */
    public function getPeakMemoryUsage(): int;

    /**
     * Retrieves the current PHP version.
     *
     * @return string PHP version.
     */
    public function getPhpVersion(): string;

    /**
     * Retrieves the type of interface between web server and PHP.
     *
     * @return string PHP SAPI name.
     */
    public function getPhpSapi(): string;

    /**
     * Retrieves the server operating system name.
     *
     * @return string OS string.
     */
    public function getServerOs(): string;
}
