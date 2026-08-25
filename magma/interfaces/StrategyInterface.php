<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\contracts\StrategyInterface as ContractStrategyInterface;

/**
 * Title: Strategy Interface Alias
 *
 * Purpose:
 * - Provides interface compatibility for modules referencing `Magma\interfaces\StrategyInterface`
 * - Acts as an alias for the core strategy contract
 *
 * Why / Why this design:
 * - Interface Segregation
 * - Allows legacy namespaces to map to the new contract namespace without breaking consumer code
 *
 * Teaching notes:
 * - Useful for maintaining backward compatibility during framework structural changes
 */
interface StrategyInterface extends ContractStrategyInterface
{
}
