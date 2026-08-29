<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * Title: ResetPasswordRequest — validation for setting a new password.
 *
 * Purpose:
 * - Enforce minimum password strength and confirmation to reduce weak
 *   credential risk during resets.
 *
 * Why / Why this design:
 * - Enforces the "Skinny Controller" principle by moving validation logic out of 
 *   the HTTP handlers and into dedicated request objects.
 *
 * Teaching notes:
 * - This architecture mimics modern frameworks (like Laravel), promoting high 
 *   reusability and clean separation of concerns.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Define the validation rules for resetting a password.
     *
     * Execution Flow:
     * 1. Extracts the incoming payload mapping.
     * 2. Evaluates the 'password' field against required and length constraints.
     * 3. Validates that a corresponding 'password_confirmation' field matches exactly.
     *
     * Logic behind the logic:
     * Enforcing a minimum length of 8 characters defends against brute-force attacks, while the 'confirmed' rule prevents user typos leading to a locked account state.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'password' => 'required|min:8|confirmed'
        ];
    }

    /**
     * Converts the validated reset password HTTP payload into a generic DTO.
     *
     * Execution Flow:
     * 1. Extracts the raw array from the request.
     * 2. Casts the array to a standard object for structural access.
     *
     * Logic behind the logic:
     * - Standardizes data transfer structure, eliminating array index probing in the consuming handlers.
     *
     * @return object
     */
    public function toDTO(): object
    {
        return (object) $this->request->request();
    }
}