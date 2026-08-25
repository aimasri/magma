<?php

namespace Magma\validation\rules;

/**
 * Title: Min Rule
 *
 * Purpose:
 * - Validates a minimum string length or a minimum numeric value.
 * 
 * Why/Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule dynamically adjusts its behavior based on the type of the value being validated.
 */
class MinRule
{
    /**
     * Executes the minimum constraint check dynamically based on the value's type.
     *
     * Execution Flow:
     * 1. Extract the minimum threshold from the rule parameters.
     * 2. If the value is a string, check its length against the minimum.
     * 3. If the value is numeric, check its numerical value against the minimum.
     *
     * Logic behind the logic:
     * - Coercing the minimum parameter safely ensures robustness, and adapting logic
     *   by type avoids the need for separate `min_length` and `min_value` rules.
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (contains the min threshold).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        $min = (int) ($params[0] ?? 0);
        if (is_int($value) || is_float($value)) {
            if ($value < $min) {
                return "The {$field} must be at least {$min}.";
            }
        } elseif (is_string($value)) {
            if (strlen($value) < $min) {
                return "The {$field} must be at least {$min} characters.";
            }
        }
        return null;
    }
}
