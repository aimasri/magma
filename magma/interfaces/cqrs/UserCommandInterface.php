<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

use Magma\domain\UserRegistration;

/**
 * Title: User Command Interface (CQRS Write Model)
 *
 * Purpose:
 * - Define the contract for mutating user identity state, password updates, role assignments, and provisioning.
 *
 * Why / Why this design:
 * - CQRS Pattern: Strictly separates user mutations from user queries (UserQueryInterface).
 * - Single Responsibility Principle (SRP): Enforces clean data boundaries for identity lifecycle management.
 *
 * Teaching notes:
 * - Used across authentication flows, user management panels, and administrative CLI seeders.
 */
interface UserCommandInterface extends CommandInterface
{
    /**
     * Creates a new user record from a validated registration domain object.
     *
     * @param UserRegistration $registration
     * @return int Generated user primary key ID.
     */
    public function create(UserRegistration $registration): int;

    /**
     * Updates an existing user's hashed password.
     *
     * @param int $userId Target user ID.
     * @param string $hashedPassword Securely hashed password string.
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void;

    /**
     * Updates the role designation for a specific user.
     *
     * @param int $userId Target user ID.
     * @param string $role Role identifier (e.g. 'admin', 'tenant', 'user').
     * @return bool True if record was updated, false otherwise.
     */
    public function updateRole(int $userId, string $role): bool;

    /**
     * Idempotently provisions an administrative user account for CLI seeding and initial setup.
     *
     * @param string $name Full name of administrator.
     * @param string $email Unique login email address.
     * @param string $hashedPassword Hashed password.
     * @param string $role Role designation (default 'admin').
     * @return int The primary key user ID.
     */
    public function provisionAdminUser(string $name, string $email, string $hashedPassword, string $role = 'admin'): int;
}
