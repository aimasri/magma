<?php

namespace Magma\validation\rules;

/**
 * Confirmed Rule
 * 
 * Purpose:
 * - Validates that a field matches a corresponding `{field}_confirmation` field.
 * 
 * Why / Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule requires access to the entire `$data` array, not just the single field value.
 */
class ConfirmedRule
{
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        $confirmationField = $field . '_confirmation';
        if (!isset($data[$confirmationField]) || $value !== $data[$confirmationField]) {
            return "The {$field} confirmation does not match.";
        }
        return null;
    }
}
