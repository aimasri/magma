<?php

namespace Magma\validation\rules;

/**
 * Required Rule
 * 
 * Purpose:
 * - Ensures a given field is present and not empty.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - The `__invoke` magic method allows instances of this class to be executed like functions.
 */
class RequiredRule
{
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        if (empty($value) && $value !== '0') {
            return "The {$field} field is required.";
        }
        return null;
    }
}
