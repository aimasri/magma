<?php

declare(strict_types=1);

namespace Magma\contracts;

/**
 * Title: Pluggable Strategy Contract Interface
 *
 * Purpose:
 * - Define the contract for pluggable domain algorithms and calculation strategies within the Magma framework.
 * - Provide key-matching (`supports`) and human-readable identification (`getName`) for dynamic resolution.
 *
 * Why / Why this design:
 * - Implements the Strategy Pattern adhering strictly to the Open/Closed Principle (OCP).
 * - Eliminates monolithic `switch` statements and conditional branching in domain services, allowing 
 *   tenant-custom pricing, taxation, inventory valuation, or allergen algorithms to be registered dynamically.
 *
 * Teaching notes:
 * - Strategies declare their compatibility via `supports($key)` so strategy registries can auto-wire 
 *   and select the appropriate engine at runtime.
 */
interface StrategyInterface
{
    /**
     * Determines whether this strategy supports the given domain key or identifier.
     *
     * @param string $key Strategy identifier, rule type, or configuration key.
     * @return bool True if this strategy handles the key, false otherwise.
     */
    public function supports(string $key): bool;

    /**
     * Returns the unique or human-readable name of the strategy implementation.
     *
     * @return string E.g. 'StandardPercentagePricingStrategy'.
     */
    public function getName(): string;
}
