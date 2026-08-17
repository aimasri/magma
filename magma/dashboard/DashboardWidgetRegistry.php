<?php

declare(strict_types=1);

namespace Magma\dashboard;

use Magma\view\dashboard\DashboardWidgetRegistry as BaseDashboardWidgetRegistry;

/**
 * Title: Dashboard Widget Registry (Kernel Namespace Alias)
 *
 * Purpose:
 * - Provide a backward-compatible alias or domain-specific wrapper for the view-level DashboardWidgetRegistry.
 *
 * Why / Why this design:
 * - Ensures legacy namespaces continue to resolve while logic is migrated to the `view` namespace.
 *
 * Teaching notes:
 * - Deprecated: Developers should type-hint `Magma\view\dashboard\DashboardWidgetRegistry` directly.
 */
class DashboardWidgetRegistry extends BaseDashboardWidgetRegistry
{
}
