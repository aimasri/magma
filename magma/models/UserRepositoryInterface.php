<?php

namespace Magma\models;

/**
 * User Identity Data Access Contract
 *
 * Purpose:
 * - Defines the strict contract for user identity storage, retrieval, and updates.
 * - Enforces the Dependency Inversion Principle across authentication and registration services.
 *
 * Why / Why this design:
 * - By depending on this interface rather than the concrete `UserRepository` class, the transport 
 *   and service layers become decoupled from the database implementation. This makes it trivial 
 *   to swap out the database technology (e.g., to an ORM or a NoSQL datastore) or to inject 
 *   an in-memory mock during unit testing.
 *
 * Teaching notes:
 * - Interfaces in the domain layer should be defined from the perspective of the *client* 
 *   (the service that needs the data), not the database. Notice how `findForAuth` is specific 
 *   to the needs of the `AuthenticationService`.
 */
interface UserRepositoryInterface
{
    /**
     * Retrieve a user by their email address without sensitive data.
     *
     * Execution Flow:
     * 1. Accepts a plain string email parameter.
     * 2. Queries the underlying data store for an exact match.
     * 3. Projects the result into an array, explicitly omitting fields like password hashes.
     *
     * Logic behind the logic:
     * Omitting sensitive data by default defends against accidental data leakage when serializing user profiles for API responses or views.
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Retrieve a user by their email address including their password hash.
     *
     * Execution Flow:
     * 1. Queries the data store for a user matching the provided email.
     * 2. Ensures the resulting dataset includes the hashed authentication payload.
     *
     * Logic behind the logic:
     * Creates a strict segregation between standard user retrieval and authentication retrieval, preventing passwords from leaking into generic repository methods.
     *
     * @param string $email
     * @return array|null
     */
    public function findForAuth(string $email): ?array;

    /**
     * Retrieve a user by their primary key without sensitive data.
     *
     * Execution Flow:
     * 1. Accepts the internal primary key identifier.
     * 2. Queries the database using an indexed lookup.
     * 3. Projects a sanitized user array format.
     *
     * Logic behind the logic:
     * ID-based lookups are highly optimized in relational stores and provide a direct path for session-based user reinstatement.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * Insert a new user into the database.
     *
     * Execution Flow:
     * 1. Takes a domain-specific Data Transfer Object (DTO) to ensure parameter safety.
     * 2. Maps the DTO properties to the underlying schema.
     * 3. Executes the insertion and captures the resulting auto-incrementing key.
     *
     * Logic behind the logic:
     * Using a typed DTO (\Magma\domain\UserRegistration) instead of a raw array enforces strict typing and prevents mass assignment vulnerabilities at the domain boundary.
     *
     * @param \Magma\domain\UserRegistration $registration
     * @return int
     */
    public function create(\Magma\domain\UserRegistration $registration): int;

    /**
     * Update an existing user's password.
     *
     * Execution Flow:
     * 1. Accepts the user's primary key and a pre-hashed password string.
     * 2. Dispatches an update query targeting the specific row.
     * 3. Persists the new credential to the data store.
     *
     * Logic behind the logic:
     * The method signature explicitly requires a `$hashedPassword` rather than a raw password, placing the responsibility of cryptographic hashing firmly on the application service layer, keeping the repository purely focused on data persistence.
     *
     * @param int $userId
     * @param string $hashedPassword
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void;
}
