<?php

namespace Magma\validation\rules;

/**
 * Numeric Rule
 * 
 * Purpose:
 * - Validates that a value is numeric.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 *
 * Teaching notes:
 * - Simple validation rules like this are structured as invokable classes. This allows 
 *   the framework to resolve them dynamically without hardcoding a massive switch 
 *   statement inside the Validator class.
 */
class NumericRule
{
    /**
     * Executes the numeric validation check.
     *
     * Execution Flow:
     * 1. Check if the provided value passes PHP's `is_numeric` check.
     * 2. If it fails, return a formatted error message.
     * 3. If it passes, return null indicating successful validation.
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (unused here).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        if (!is_numeric($value)) {
            return "The {$field} must be a number.";
        }
        return null;
    }
}
