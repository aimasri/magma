<?php

namespace App\services;

use App\dto\SystemDiagnosticsDTO;
use Magma\config\ConfigInterface;
use App\constants\AppConstants;
use App\providers\SystemInfoProviderInterface;

/**
 * Title: System Diagnostics Service
 *
 * Purpose:
 * - Collects system diagnostic logic (memory usage, OS info) out of controllers.
 *
 * Why / Why this design:
 * - Adheres to SRP by removing infrastructure logic from controllers.
 *
 * Teaching notes:
 * - Returns a DTO to maintain strict data boundaries.
 */
class SystemDiagnosticsService
{
    private ConfigInterface $config;
    private SystemInfoProviderInterface $systemInfoProvider;

    public function __construct(ConfigInterface $config, SystemInfoProviderInterface $systemInfoProvider)
    {
        $this->config = $config;
        $this->systemInfoProvider = $systemInfoProvider;
    }

    /**
     * Gathers diagnostic information and returns a DTO.
     *
     * Execution Flow:
     * 1. Retrieves memory peak usage.
     * 2. Reads configuration variables.
     * 3. Constructs and returns the DTO.
     *
     * Logic behind the logic:
     * - Transforming byte counts to human-readable megabytes isolates formatting 
     *   from the presentation layer. Abstracting the environment configuration prevents 
     *   tight coupling to global functions.
     *
     * @return SystemDiagnosticsDTO
     */
    public function getDiagnostics(): SystemDiagnosticsDTO
    {
        $memoryBytes = $this->systemInfoProvider->getPeakMemoryUsage();
        $memoryMb = round($memoryBytes / AppConstants::MEGABYTE_IN_BYTES, 2);

        $env = $this->config->get(AppConstants::ENV_APP_ENV, AppConstants::ENV_DEFAULT_DEVELOPMENT);
        $environment = is_scalar($env) ? (string) $env : AppConstants::ENV_DEFAULT_DEVELOPMENT;
        
        $db = $this->config->get(AppConstants::ENV_DB_DRIVER, AppConstants::DB_DEFAULT_PGSQL);
        $dbDriver = is_scalar($db) ? (string) $db : AppConstants::DB_DEFAULT_PGSQL;

        return new SystemDiagnosticsDTO(
            phpVersion: $this->systemInfoProvider->getPhpVersion(),
            phpSapi: $this->systemInfoProvider->getPhpSapi(),
            environment: $environment,
            debug: filter_var($this->config->get(AppConstants::ENV_APP_DEBUG, 'true'), FILTER_VALIDATE_BOOLEAN),
            dbDriver: $dbDriver,
            memoryUsage: "{$memoryMb} MB",
            serverOs: $this->systemInfoProvider->getServerOs()
        );
    }
}
