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
    /**
     * Defines the validation rules for the forgot password request.
     *
     * Execution Flow:
     * 1. Returns an array enforcing that the 'email' field is required and formatted correctly.
     *
     * Logic behind the logic:
     * - Protects the password reset flow by immediately blocking requests lacking a syntactically valid email format, reducing unnecessary database queries.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email'
        ];
    }

    /**
     * Converts the validated HTTP request into a data transfer object.
     *
     * Execution Flow:
     * 1. Extracts the raw request data array.
     * 2. Casts the array to a generic standard object.
     *
     * Logic behind the logic:
     * - Provides a clean, albeit generic, object-oriented wrapper for accessing request data in subsequent layers, avoiding array access logic spread throughout controllers.
     *
     * @return object
     */
    public function toDTO(): object
    {
        return (object) $this->request->request();
    }
}