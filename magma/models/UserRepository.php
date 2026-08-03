<?php

namespace Magma\models;

/**
 * User Identity Data Access
 *
 * Purpose:
 * - Handle primary user CRUD operations (creation, fetching, and password updates).
 * - Isolate SQL statements interacting with the `users` table.
 *
 * Why / Why this design:
 * - Implements the Repository pattern and the Dependency Inversion Principle via `UserRepositoryInterface`.
 *   By keeping SQL queries out of controllers and services, and enforcing the interface contract,
 *   we make the business logic completely unaware of the underlying database technology 
 *   (e.g., PostgreSQL vs MySQL).
 *
 * Teaching notes:
 * - This class strictly handles core identity data. Token management (Remember Me and 
 *   Password Reset) has been extracted to dedicated repositories to prevent feature-bloat 
 *   and adhere to the Single Responsibility Principle.
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{


    /**
     * Retrieve a user by their email address without sensitive data.
     * 
     * Execution Flow:
     * 1. Query the database for a user matching the provided email.
     * 2. Explicitly select only non-sensitive columns (id, name, email, role).
     * 3. Return the user array or null if not found.
     * 
     * Logic behind the logic:
     * - Excluding the password hash prevents highly sensitive data from accidentally 
     *   being dumped into the user's cookie/session cache payload during general lookups.
     */
    public function findByEmail(string $email): ?\Magma\domain\AuthUser
    {
        $stmt = $this->dbRead->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new \Magma\domain\AuthUser($row) : null;
    }

    /**
     * Retrieve a user by their email address including their password hash.
     * 
     * Execution Flow:
     * 1. Query the database for a user matching the provided email.
     * 2. Explicitly select non-sensitive columns AND the password hash.
     * 3. Return the user array or null if not found.
     * 
     * Logic behind the logic:
     * - This method is strictly reserved for the Authentication layer. By having a 
     *   dedicated method for fetching the password, we ensure the hash is only 
     *   pulled into memory when absolutely necessary for verification.
     */
    public function findForAuth(string $email): ?array
    {
        $stmt = $this->dbRead->prepare("SELECT id, name, email, role, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Retrieve a user by their primary key without sensitive data.
     *
     * Purpose:
     * - Fetches user details based on internal ID rather than email.
     *
     * Logic behind the logic:
     * - Consistent with `findByEmail`, this intentionally omits the password hash to maintain strict security boundaries for general data loads.
     *
     * @param int $id The user's ID.
     */
    public function findById(int $id): ?\Magma\domain\AuthUser
    {
        $stmt = $this->dbRead->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new \Magma\domain\AuthUser($row) : null;
    }

    /**
     * Insert a new user into the database.
     *
     * Purpose:
     * - Persists a new user registration to the `users` table.
     *
     * Execution Flow:
     * 1. Prepares the INSERT statement.
     * 2. Executes the query using the Write connection.
     * 3. Returns the newly generated primary key.
     *
     * Logic behind the logic:
     * - By enforcing a typed domain entity `UserRegistration`, we guarantee the caller has already satisfied validation constraints before writing to DB.
     *
     * @param \Magma\domain\UserRegistration $registration The encapsulated registration data.
     * @return int The ID of the newly created user.
     */
    public function create(\Magma\domain\UserRegistration $registration): int
    {
        $stmt = $this->dbWrite->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([
            $registration->getName(),
            $registration->getEmail(),
            $registration->getHashedPassword()
        ]);
        return (int) $this->dbWrite->lastInsertId();
    }

    /**
     * Update an existing user's password.
     *
     * Purpose:
     * - Modifies the password hash for a specific user after a password reset 
     *   or a manual password change request.
     *
     * Logic behind the logic:
     * - Modifying credentials shouldn't invoke the entire update mechanism of the model. 
     *   A strict, isolated method prevents unintentional overwriting of other user fields.
     *
     * @param int $userId The primary key of the user.
     * @param string $hashedPassword The new pre-hashed password.
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $stmt = $this->dbWrite->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }
}