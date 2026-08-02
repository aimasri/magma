<?php

namespace Magma\validation\rules;

/**
 * Confirmed Rule
 * 
 * Purpose:
 * - Validates that a field matches a corresponding `{field}_confirmation` field.
 * 
 * Why/Why this design:
 * - Extracted into a callable class to support the Open/Closed Principle in the Validator.
 * 
 * Teaching notes:
 * - This rule requires access to the entire `$data` array, not just the single field value.
 */
class ConfirmedRule
{
    /**
     * Executes the confirmed constraint check against a confirmation field.
     *
     * Execution Flow:
     * 1. Construct the expected confirmation field name (e.g., password_confirmation).
     * 2. Verify that the confirmation field exists in the data payload.
     * 3. Assert that both values strictly match.
     *
     * Logic behind the logic:
     * - Strict checking is used to avoid type coercion vulnerabilities (e.g., '123' == 123).
     *
     * @param string $field The name of the field under validation.
     * @param mixed $value The value to validate.
     * @param array $params Additional parameters (unused).
     * @param array $data The full dataset being validated.
     * @return string|null The error message, or null if valid.
     */
    public function __invoke(string $field, mixed $value, array $params, array $data): ?string
    {
        $confirmationField = $field . '_confirmation';
        if (!isset($data[$confirmationField]) || $value !== $data[$confirmationField]) {
            return "The {$field} confirmation does not match.";
        }
        return null;
    }
}
