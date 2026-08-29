<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

/**
 * Title: User Query Repository Interface
 *
 * Purpose:
 * - Define the read-only contract for retrieving user records from the database.
 * - Serve as an abstraction boundary for authentication and user-fetching logic.
 *
 * Why / Why this design:
 * - CQRS (Command Query Responsibility Segregation): Separates read models (Queries) from write models (Commands).
 * - DIP (Dependency Inversion Principle): Services depend on this interface, not the concrete SQL implementation.
 *
 * Teaching notes:
 * - The `findForAuth` method typically returns a raw array containing the hashed password for verification, while other methods return safe `AuthUser` domain objects.
 */
interface UserQueryInterface extends \Magma\interfaces\cqrs\QueryInterface
{
    /**
     * Finds a user by their email address, returning a domain entity.
     *
     * @param string $email
     * @return \Magma\domain\AuthUser|null
     */
    public function findByEmail(string $email): ?\Magma\domain\AuthUser;
    
    /**
     * Retrieves raw user authentication data (including hashed passwords) for verification.
     *
     * @param string $email
     * @return array<string, mixed>|null
     */
    public function findForAuth(string $email): ?array;
    
    /**
     * Finds a user by their unique ID, returning a domain entity.
     *
     * @param int $id
     * @return \Magma\domain\AuthUser|null
     */
    public function findById(int $id): ?\Magma\domain\AuthUser;
    
    /**
     * Retrieves the UNIX timestamp of when the user's password was last changed.
     * 
     * @param int $id The user ID
     * @return int|null UNIX timestamp or null if never changed.
     */
    public function getPasswordChangedAt(int $id): ?int;
}
