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
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Retrieve a user by their email address including their password hash.
     *
     * @param string $email
     * @return array|null
     */
    public function findForAuth(string $email): ?array;

    /**
     * Retrieve a user by their primary key without sensitive data.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * Insert a new user into the database.
     *
     * @param \Magma\domain\UserRegistration $registration
     * @return int
     */
    public function create(\Magma\domain\UserRegistration $registration): int;

    /**
     * Update an existing user's password.
     *
     * @param int $userId
     * @param string $hashedPassword
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void;
}
