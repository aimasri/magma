<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: RegistrationDTO
 *
 * Purpose:
 * - Data Transfer Object containing user registration data.
 * - Transports validated registration input from the controller to the service layer.
 *
 * Why / Why this design:
 * - DTO pattern decouples the framework's HTTP layer from the application's domain services.
 * - Enforces strong typing for registration payload.
 *
 * Teaching notes:
 * - Readonly fields ensure the data is not mutated during the registration flow.
 */
readonly class RegistrationDTO
{
    /**
     * Initializes the RegistrationDTO with name, email, and password.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}
}
