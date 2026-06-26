<?php

namespace Magma\domain;

/**
 * User Registration Domain Entity
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
     * 2. Extracts the plain-text password.
     * 3. Immediately hashes the plain-text password using bcrypt.
     *
     * Logic behind the logic:
     * - Trimming whitespace prevents accidental validation failures due to trailing spaces.
     * - Hashing the password in the constructor ensures that the plain-text password 
     *   never exists as a readable property within the domain layer, limiting exposure 
     *   during debugging or error logging.
     *
     * @param array $data The raw input array, typically from a POST request.
     */
    public function __construct(array $data)
    {
        $this->name = trim($data['name'] ?? '');
        $this->email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        $this->hashedPassword = password_hash($password, PASSWORD_DEFAULT);
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
