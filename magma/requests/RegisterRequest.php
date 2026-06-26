<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * RegisterRequest — validation for account creation.
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
    public function rules(): array
    {
        return [
            'name'     => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed'
        ];
    }
}