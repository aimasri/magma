<?php

namespace Magma\validation\rules;

/**
 * Email Rule
 * 
 * Purpose:
 * - Validates that a string is formatted as an email address.
 * 
 * Why/Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - Relying on PHP's native `filter_var` is much safer and more accurate than attempting 
 *   to write a custom Regex for email validation.
 */
class EmailRule
{
    /**
     * Executes the email validation check.
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (unused).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} must be a valid email address.";
        }
        return null;
    }
}
