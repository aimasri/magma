<?php

namespace Magma\validation\rules;

/**
 * Title: Max Rule
 *
 * Purpose:
 * - Validates a maximum string length or a maximum numeric value.
 * 
 * Why/Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule dynamically adjusts its behavior based on the type of the value being validated.
 */
class MaxRule
{
    /**
     * Executes the maximum constraint check dynamically based on the value's type.
     *
     * Execution Flow:
     * 1. Extract the maximum threshold from the rule parameters.
     * 2. If the value is a string, check its length against the maximum.
     * 3. If the value is numeric, check its numerical value against the maximum.
     *
     * Logic behind the logic:
     * - Checking the type first ensures that numeric values aren't treated as strings
     *   for length comparisons, which would lead to incorrect logic.
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (contains the max threshold).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        $max = (int) ($params[0] ?? 0);
        if (is_string($value) && strlen($value) > $max) {
            return "The {$field} may not be greater than {$max} characters.";
        }
        if (is_numeric($value) && $value > $max) {
            return "The {$field} may not be greater than {$max}.";
        }
        return null;
    }
}
