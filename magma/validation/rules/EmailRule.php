<?php

namespace Magma\validation\rules;

/**
 * Email Rule
 * 
 * Purpose:
 * - Validates that a string is formatted as an email address.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - Relying on PHP's native `filter_var` is much safer and more accurate than attempting 
 *   to write a custom Regex for email validation.
 */
class EmailRule
{
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} must be a valid email address.";
        }
        return null;
    }
}
