<?php

declare(strict_types=1);

namespace App\services;

use App\dto\SystemDiagnosticsDTO;

/**
 * Title: System Diagnostics Service Interface
 *
 * Purpose:
 * - Provides an abstraction for gathering environmental and runtime health metrics (e.g. memory usage, PHP version, database driver).
 *
 * Why this design:
 * - Interface Segregation. The presentation layer (like the welcome dashboard) shouldn't be tightly coupled to how these metrics are gathered (whether from `getenv`, PHP built-ins, or third-party monitoring APIs).
 *
 * Teaching notes:
 * - Implementations of this interface should remain read-only and strictly avoid mutating any application state.
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
