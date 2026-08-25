<?php

declare(strict_types=1);

namespace Magma\view;

use Magma\views\ViewComposerRegistry as BaseViewComposerRegistry;

/**
 * Title: ViewComposerRegistry Alias
 *
 * Purpose:
 * - Provides namespace compatibility for ViewComposerRegistry under the `Magma\view` namespace
 * - Extends the base registry to avoid breaking changes in dependent classes using the older namespace
 *
 * Why / Why this design:
 * - Alias/Proxy Pattern: Ensures backward compatibility during framework refactoring or namespace restructuring
 * - Open/Closed Principle: Allows the framework to expand namespaces without modifying existing consumer code
 *
 * Teaching notes:
 * - Use this pattern sparingly; it's a structural bridge, primarily useful for deprecating old namespaces gracefully.
 */
class ViewComposerRegistry extends BaseViewComposerRegistry
{
}
