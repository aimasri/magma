<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\models\AbstractQueryRepository;
use Magma\domain\AuthUser;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PDO;

/**
 * Title: User Query Repository
 * Purpose:
 * - Handles specialized read operations for User domain entities.
 * Why / Why this design:
 * - Adheres to CQRS principles by inheriting from AbstractQueryRepository, restricting operations to the read-replica.
 * - Enforces separation of concerns by completely isolating SQL retrieval from business and domain logic.
 * Teaching notes:
 * - This repository ensures that User read operations are highly performant and scalable.
 */
class UserQueryRepository extends AbstractQueryRepository implements UserQueryInterface
{
    /**
     * Retrieves a user entity by their email address.
     *
     * Execution Flow:
     * 1. Prepares a SELECT statement for the 'users' table filtering by email.
     * 2. Executes the query and fetches the resulting associative array.
     * 3. Instantiates and returns an AuthUser object or null if no user is found.
     *
     * Logic behind the logic:
     * - Maps raw query data immediately into the AuthUser domain model to provide strongly typed domain access across the application.
     */
    public function findByEmail(string $email): ?AuthUser
    {
        $row = $this->fetchOne("SELECT id, name, email, role, tenant_id FROM users WHERE email = ?", [$email]);
        return $row ? new AuthUser($row) : null;
    }

    /**
     * Fetches a user's complete authentication details by email.
     *
     * Execution Flow:
     * 1. Prepares a query to retrieve user details including the hashed password.
     * 2. Executes and fetches the raw associative array.
     * 3. Returns the array directly without domain mapping.
     *
     * Logic behind the logic:
     * - Intentionally returns a raw array to expose the password hash, which is normally hidden by AuthUser. Used strictly during authentication processes to verify credentials.
     *
     * @return array<string, mixed>|null
     */
    public function findForAuth(string $email): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role, password, tenant_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($result)) {
            $row = [];
            foreach ($result as $k => $v) {
                if (is_string($k)) {
                    $row[$k] = $v;
                }
            }
            return $row;
        }
        return null;
    }

    /**
     * Retrieves a user entity by their unique identifier.
     *
     * Execution Flow:
     * 1. Prepares a SELECT statement targeting the given user ID.
     * 2. Executes the query and maps the resulting row to an AuthUser object.
     * 3. Returns null if the user does not exist.
     *
     * Logic behind the logic:
     * - Simple lookup by primary key encapsulating the read operation away from application logic.
     */
    public function findById(int $id): ?AuthUser
    {
        $row = $this->fetchOne("SELECT id, name, email, role, tenant_id FROM users WHERE id = ?", [$id]);
        return $row ? new AuthUser($row) : null;
    }

    /**
     * Retrieves the timestamp of when the user's password was last modified.
     *
     * Execution Flow:
     * 1. Prepares and executes a query fetching only the password_changed_at column for the given ID.
     * 2. Checks if a value exists, returning null otherwise.
     * 3. Parses the database date string explicitly in the UTC timezone context and converts it to a UNIX timestamp.
     *
     * Logic behind the logic:
     * - Allows efficient checking of password modification times (useful for invalidating active sessions) without loading the entire user model into memory.
     * - Explicitly parsing the date string with a UTC DateTimeZone ensures deterministic timestamp resolution regardless of the PHP runtime's default timezone.
     */
    public function getPasswordChangedAt(int $id): ?int
    {
        $stmt = $this->getDb()->prepare("SELECT password_changed_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        if (!$val) {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $val, new DateTimeZone('UTC'));
            return $date->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }
}
