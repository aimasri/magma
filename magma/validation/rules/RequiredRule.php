<?php

namespace Magma\validation\rules;

/**
 * Required Rule
 * 
 * Purpose:
 * - Ensures a given field is present and not empty.
 * 
 * Why/Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - The `__invoke` magic method allows instances of this class to be executed like functions.
 */
class RequiredRule
{
    /**
     * Executes the required validation check.
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (unused).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        if (empty($value) && !in_array($value, [0, 0.0, '0'], true)) {
            return "The {$field} field is required.";
        }
        return null;
    }
}
