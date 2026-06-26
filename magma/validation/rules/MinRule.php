<?php

namespace Magma\validation\rules;

/**
 * Min Rule
 * 
 * Purpose:
 * - Validates a minimum string length or a minimum numeric value.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule dynamically adjusts its behavior based on the type of the value being validated.
 */
class MinRule
{
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        $min = (int) ($params[0] ?? 0);
        if (is_string($value) && strlen($value) < $min) {
            return "The {$field} must be at least {$min} characters.";
        }
        if (is_numeric($value) && $value < $min) {
            return "The {$field} must be at least {$min}.";
        }
        return null;
    }
}
