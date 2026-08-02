<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * Title: Login Request Validation
 *
 * Purpose:
 * - Declares concise validation rules for authentication attempts.
 * - Allows controllers to reuse the FormRequest/Validator flow.
 *
 * Why this design:
 * - Enforces the "Skinny Controller" principle by moving validation logic out of the HTTP handlers and into dedicated request objects.
 *
 * Teaching notes:
 * - This architecture mimics modern frameworks (like Laravel), promoting high reusability and clean separation of concerns.
 */
class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required'
        ];
    }
}