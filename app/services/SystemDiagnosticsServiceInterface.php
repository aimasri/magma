<?php

declare(strict_types=1);

namespace App\services;

use App\dto\SystemDiagnosticsDTO;

/**
 * Title: System Diagnostics Service Interface
 */
interface SystemDiagnosticsServiceInterface
{
    /**
     * Retrieves current system diagnostic metrics.
     *
     * @return SystemDiagnosticsDTO
     */
    public function getDiagnostics(): SystemDiagnosticsDTO;
}
