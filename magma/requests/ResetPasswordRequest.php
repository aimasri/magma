<?php

namespace Magma\requests;

use Magma\validation\FormRequest;

/**
 * ResetPasswordRequest — validation for setting a new password.
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
    public function rules(): array
    {
        return [
            'password' => 'required|min:8|confirmed'
        ];
    }
}