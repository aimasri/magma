<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: LoginDTO
 *
 * Purpose:
 * - Data Transfer Object containing login credentials.
 * - Carries validated input data from the Request to the Service layer.
 *
 * Why / Why this design:
 * - DTO pattern ensures type safety and avoids passing entire Request objects to domain layers.
 * - Makes the authentication service agnostic to HTTP framework details.
 *
 * Teaching notes:
 * - Being a readonly class ensures immutability of the credentials once validated.
 */
readonly class LoginDTO
{
    /**
     * Initializes the LoginDTO with email, password, and remember-me flag.
     */
    public function __construct(
        public string $email,
        public string $password,
        public bool $rememberMe
    ) {}
}
