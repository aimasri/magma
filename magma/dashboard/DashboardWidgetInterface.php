<?php

declare(strict_types=1);

namespace Magma\dashboard;

use Magma\interfaces\dashboard\DashboardWidgetInterface as BaseDashboardWidgetInterface;

/**
 * Title: Dashboard Widget Interface (Kernel Namespace Alias)
 *
 * Purpose:
 * - Define the contract for dashboard widgets in a backward-compatible namespace.
 *
 * Why / Why this design:
 * - Ensures legacy modules continue to function during the interface migration.
 *
 * Teaching notes:
 * - Deprecated: Developers should type-hint `Magma\interfaces\dashboard\DashboardWidgetInterface` directly.
 */
interface DashboardWidgetInterface extends BaseDashboardWidgetInterface
{
}
