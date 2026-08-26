<?php

namespace App\dto;

/**
 * Title: System Diagnostics DTO
 *
 * Purpose:
 * - Data transfer object for system diagnostics data.
 *
 * Why / Why this design:
 * - Separates diagnostic data structure from controllers.
 * - Solidifies boundaries by returning strongly typed objects.
 *
 * Teaching notes:
 * - Readonly properties make it immutable.
 */
readonly class SystemDiagnosticsDTO implements \JsonSerializable
{
    /**
     * Initializes the DTO.
     * 
     * @param string $phpVersion The PHP version.
     * @param string $phpSapi The PHP Server API type.
     * @param string $environment The application environment.
     * @param bool $debug True if debug mode is on.
     * @param string $dbDriver The database driver in use.
     * @param int $memoryUsageBytes Peak memory usage in bytes.
     * @param string $serverOs The operating system string.
     */
    public function __construct(
        public string $phpVersion,
        public string $phpSapi,
        public string $environment,
        public bool $debug,
        public string $dbDriver,
        public int $memoryUsageBytes,
        public string $serverOs
    ) {}

    /**
     * Serializes the DTO into an associative array for JSON encoding.
     * 
     * @return array<string, mixed> Associative array of DTO properties.
     */
    public function jsonSerialize(): array
    {
        return [
            'phpVersion'  => $this->phpVersion,
            'phpSapi'     => $this->phpSapi,
            'environment' => $this->environment,
            'debug'       => $this->debug,
            'dbDriver'    => $this->dbDriver,
            'memoryUsageBytes' => $this->memoryUsageBytes,
            'serverOs'    => $this->serverOs,
        ];
    }
}
