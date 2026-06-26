<?php

namespace Magma\validation\rules;

/**
 * Max Rule
 * 
 * Purpose:
 * - Validates a maximum string length or a maximum numeric value.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule dynamically adjusts its behavior based on the type of the value being validated.
 */
class MaxRule
{
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
