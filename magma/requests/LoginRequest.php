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
    /**
     * Defines the validation rules for an authentication attempt.
     *
     * Execution Flow:
     * 1. Returns an array enforcing the presence of 'email' and 'password' fields.
     * 2. Asserts 'email' matches valid RFC formatting and 'password' does not exceed maximum bounds.
     *
     * Logic behind the logic:
     * - Bounding the password length prevents potential Denial of Service (DoS) attacks via extreme length hashing limits in bcrypt/argon2.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|max:100'
        ];
    }

    /**
     * Transforms validated request inputs into a strongly-typed LoginDTO.
     *
     * Execution Flow:
     * 1. Retrieves the raw input array from the underlying HTTP request.
     * 2. Extracts and sanitizes the 'email' and 'password' fields, falling back to empty strings if missing or invalid.
     * 3. Determines the 'remember_me' boolean state.
     * 4. Instantiates and returns a LoginDTO.
     *
     * Logic behind the logic:
     * - The DTO acts as an anti-corruption layer, ensuring the authentication service only works with well-defined, predictable structures instead of arbitrary arrays.
     *
     * @return \Magma\dto\LoginDTO
     */
    public function toDTO(): \Magma\dto\LoginDTO
    {
        $data = $this->request->request();
        $email = is_array($data) && isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
        $password = is_array($data) && isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
        $rememberMe = is_array($data) && !empty($data['remember_me']);

        return new \Magma\dto\LoginDTO(
            email: trim($email),
            password: $password,
            rememberMe: $rememberMe
        );
    }
}