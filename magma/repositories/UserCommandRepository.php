<?php

declare(strict_types=1);

namespace Magma\repositories;

use PDO;
use PDOException;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\models\AbstractCommandRepository;
use Magma\domain\UserRegistration;
use Magma\domain\exceptions\DuplicateResourceException;

/**
 * Title: User Command Repository (CQRS Write Model)
 *
 * Purpose:
 * - Handle state-mutating database operations for user entities.
 * - Enforce atomic PostgreSQL inserts, secure password updates, role changes, and admin provisioning.
 *
 * Why / Why this design:
 * - Extends `AbstractCommandRepository` to strictly direct all mutations to the Master Write database connection.
 * - Implements `UserCommandInterface` ensuring loose coupling and testability.
 *
 * Teaching notes:
 * - Catches SQL state unique violation codes ('23000' and '23505') and translates them into domain exceptions.
 */
class UserCommandRepository extends AbstractCommandRepository implements UserCommandInterface
{
    /**
     * Creates a new user record from a UserRegistration domain entity.
     *
     * Execution Flow:
     * 1. Delegate to insertAndGetId() with table 'users'.
     * 2. Catch PDOException and translate unique constraint violations (codes '23000'/'23505') 
     *    into DuplicateResourceException.
     * 3. Return the generated user ID.
     *
     * @param UserRegistration $registration
     * @return int
     * @throws DuplicateResourceException If email is already in use.
     * @throws PDOException
     */
    public function create(UserRegistration $registration): int
    {
        try {
            return $this->insertAndGetId('users', [
                'name' => $registration->getName(),
                'email' => $registration->getEmail(),
                'password' => $registration->getHashedPassword(),
            ]);
        } catch (PDOException $e) {
            $code = (string) $e->getCode();
            if ($code === '23000' || $code === '23505') {
                throw new DuplicateResourceException('This email is already registered.', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Updates an existing user's password hash.
     *
     * @param int $userId
     * @param string $hashedPassword
     * @return void
     */
    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $stmt = $this->getDb()->prepare("UPDATE \"users\" SET \"password\" = ?, \"updated_at\" = NOW() WHERE \"id\" = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }

    /**
     * Updates the role designation for a user.
     *
     * @param int $userId Target user ID.
     * @param string $role Role identifier ('admin', 'tenant', 'user').
     * @return bool True if updated, false otherwise.
     */
    public function updateRole(int $userId, string $role): bool
    {
        $stmt = $this->getDb()->prepare("UPDATE \"users\" SET \"role\" = ?, \"updated_at\" = NOW() WHERE \"id\" = ?");
        $stmt->execute([$role, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Provisions an administrator user account idempotently for CLI seeders and bootstrap routines.
     *
     * Execution Flow:
     * 1. Check if a user with the provided email already exists.
     * 2. If existing, update the name, password, and role to ensure administrative access, and return existing ID.
     * 3. If new, insert the administrator record and return the generated ID.
     *
     * @param string $name
     * @param string $email
     * @param string $hashedPassword
     * @param string $role
     * @return int The user ID.
     */
    public function provisionAdminUser(string $name, string $email, string $hashedPassword, string $role = 'admin'): int
    {
        $stmt = $this->getDb()->prepare("SELECT \"id\" FROM \"users\" WHERE \"email\" = ?");
        $stmt->execute([$email]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false && $existingId !== null) {
            $userId = (int) $existingId;
            $updateStmt = $this->getDb()->prepare(
                "UPDATE \"users\" SET \"name\" = ?, \"password\" = ?, \"role\" = ?, \"updated_at\" = NOW() WHERE \"id\" = ?"
            );
            $updateStmt->execute([$name, $hashedPassword, $role, $userId]);
            return $userId;
        }

        return $this->insertAndGetId('users', [
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role,
        ]);
    }
}
