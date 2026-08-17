<?php

declare(strict_types=1);

namespace Magma\domain;

/**
 * Title: User Registration Domain Entity
 *
 * Purpose:
 * - Encapsulate the core data and behavior of a new user registration.
 * - Centralize sanitization and secure password hashing.
 *
 * Why / Why this design:
 * - Domain-Driven Design (DDD): By moving array extraction and password hashing 
 *   from the `RegistrationService` into this entity, the service remains a thin 
 *   orchestrator.
 * - Strictly typed properties ensure explicit state.
 *
 * Teaching notes:
 * - Entities never query the database, so uniqueness checks (like duplicate emails) 
 *   still happen in the service, but the entity guarantees the data is well-formed.
 * - Password hashing is injected into the entity during construction to avoid hidden side effects.
 */
class UserRegistration
{
    private string $name;
    private string $email;
    private string $hashedPassword;

    /**
     * Constructs a new UserRegistration entity.
     *
     * Execution Flow:
     * 1. Extracts the name and email from the raw data array, trimming whitespace.
     * 2. Sets the already-hashed password.
     *
     * Logic behind the logic:
     * - Trimming whitespace prevents accidental validation failures due to trailing spaces.
     * - Injecting the hashed password ensures the entity is fully decoupled from the hashing mechanism.
     *
     * @param string $name The user's name.
     * @param string $email The user's email.
     * @param string $hashedPassword The pre-hashed password.
     */
    public function __construct(string $name, string $email, string $hashedPassword)
    {
        $this->name = trim($name);
        $this->email = trim($email);
        $this->hashedPassword = $hashedPassword;
    }

    /**
     * Retrieves the sanitized user name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the sanitized email address.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Retrieves the cryptographically secure hashed password.
     *
     * @return string
     */
    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }
}
