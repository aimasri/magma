<?php

declare(strict_types=1);

namespace App\providers;

/**
 * Title: System Info Provider
 *
 * Purpose:
 * - Implementation of SystemInfoProviderInterface that wraps global system functions.
 *
 * Why / Why this design:
 * - Adheres to SOLID, allowing dependencies to be injected.
 *
 * Teaching notes:
 * - Directly wraps built-in PHP functions.
 */
class SystemInfoProvider implements SystemInfoProviderInterface
{
    /**
     * Retrieves the peak memory usage of the PHP script in bytes.
     *
     * @return int Memory peak usage in bytes.
     */
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * Retrieves the current PHP version.
     *
     * @return string PHP version.
     */
    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * Retrieves the type of interface between web server and PHP.
     *
     * @return string PHP SAPI name.
     */
    public function getPhpSapi(): string
    {
        return PHP_SAPI;
    }

    /**
     * Retrieves the server operating system name.
     *
     * @return string OS string.
     */
    public function getServerOs(): string
    {
        return PHP_OS . ' (' . php_uname('m') . ')';
    }
}
