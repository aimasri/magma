<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * Title: RegisterRequest — validation for account creation.
 *
 * Purpose:
 * - Ensure required fields and password confirmation are present and valid.
 *
 * Why / Why this design:
 * - Enforces the "Skinny Controller" principle by moving validation logic out of 
 *   the HTTP handlers and into dedicated request objects.
 *
 * Teaching notes:
 * - This architecture mimics modern frameworks (like Laravel), promoting high 
 *   reusability and clean separation of concerns.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Define the validation rules for user registration.
     *
     * Execution Flow:
     * 1. Asserts the 'name' field is provided.
     * 2. Validates 'email' against standard formatting rules.
     * 3. Evaluates 'password' for minimum complexity and confirmation match.
     *
     * Logic behind the logic:
     * The 'confirmed' rule prevents user lockouts immediately after registration by verifying intent. The email rule delegates complex RFC 822 compliance checks to the underlying validation engine.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'     => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed'
        ];
    }

    public function toDTO(): object
    {
        return (object) $this->request->request();
    }
}