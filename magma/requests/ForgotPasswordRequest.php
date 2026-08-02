<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * Title: Forgot Password Request Validation
 *
 * Purpose:
 * - Ensures an email is provided and correctly formatted before creating a password reset token.
 *
 * Why this design:
 * - Enforces the "Skinny Controller" principle by moving validation logic out of the HTTP handlers and into dedicated request objects.
 *
 * Teaching notes:
 * - This architecture mimics modern frameworks (like Laravel), promoting high reusability and clean separation of concerns.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email'
        ];
    }
}