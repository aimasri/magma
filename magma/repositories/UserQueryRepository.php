<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\models\AbstractQueryRepository;
use Magma\domain\AuthUser;
use Magma\database\DatabaseConnectionManager;
use Magma\security\AuthTenantMode;
use Magma\security\TenantContext;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PDO;

/**
 * Title: User Query Repository
 *
 * Purpose:
 * - Handles specialized read operations for User authentication and session identity entities.
 * - Enforces configurable multi-tenant isolation (`AuthTenantMode::TenantScoped` vs `AuthTenantMode::GlobalGateway`).
 *
 * Why / Why this design:
 * - Adheres to CQRS principles by inheriting from `AbstractQueryRepository`, restricting operations to the read-replica.
 * - Enforces separation of concerns by isolating SQL retrieval and tenant-mode branching away from authentication services.
 *
 * Teaching notes:
 * - In `TenantScoped` mode (default), every query restricts results to the active domain's `tenant_id` (or global `NULL` superadmins).
 * - In `GlobalGateway` mode, central `/login`, `/forgot-password`, and token auto-login queries resolve globally unique user identities before `TenantSecurityMiddleware` binds the session's verified `tenant_id`.
 */
class UserQueryRepository extends AbstractQueryRepository implements UserQueryInterface
{
    /**
     * Initializes the User Query Repository with connection manager, tenant context, and authentication scoping mode.
     *
     * @param DatabaseConnectionManager $dbManager
     * @param TenantContext|null $tenantContext
     * @param AuthTenantMode $authMode
     */
    public function __construct(
        DatabaseConnectionManager $dbManager,
        ?TenantContext $tenantContext = null,
        private readonly AuthTenantMode $authMode = AuthTenantMode::TenantScoped
    ) {
        parent::__construct($dbManager, $tenantContext);
    }

    /**
     * Retrieves a user entity by their email address.
     *
     * Execution Flow:
     * 1. Prepares a SELECT statement for the `users` table filtering by email.
     * 2. Appends tenant isolation predicate when operating in `AuthTenantMode::TenantScoped`.
     * 3. Executes the query and maps the resulting row into an `AuthUser` value entity.
     *
     * Logic behind the logic:
     * - Maps raw query data immediately into the `AuthUser` domain model so password hashes are never exposed outside credential verification.
     */
    public function findByEmail(string $email): ?AuthUser
    {
        $sql = "SELECT id, name, email, role, tenant_id FROM users WHERE email = ?";
        $params = [$email];

        if ($this->authMode === AuthTenantMode::TenantScoped) {
            $tenantId = $this->getTenantId();
            if ($tenantId !== null) {
                $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)";
                $params[] = $tenantId;
            }
        }

        $row = $this->fetchOne($sql, $params);
        return $row ? new AuthUser($row) : null;
    }

    /**
     * Fetches a user's complete authentication details by email.
     *
     * Execution Flow:
     * 1. Prepares a query to retrieve user details including the hashed password.
     * 2. Appends tenant scoping in `TenantScoped` mode, or queries globally by unique email in `GlobalGateway` mode.
     * 3. Executes and fetches the raw associative array.
     *
     * Logic behind the logic:
     * - Intentionally returns a raw array to expose the password hash strictly to `AuthenticationService`.
     *
     * @return array<string, mixed>|null
     */
    public function findForAuth(string $email): ?array
    {
        $sql = "SELECT id, name, email, role, password, tenant_id FROM users WHERE email = ?";
        $params = [$email];

        if ($this->authMode === AuthTenantMode::TenantScoped) {
            $tenantId = $this->getTenantId();
            if ($tenantId !== null) {
                $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)";
                $params[] = $tenantId;
            }
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
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
     * 2. Applies tenant scoping when operating in `TenantScoped` mode.
     * 3. Executes the query and maps the resulting row to an `AuthUser` object.
     *
     * Logic behind the logic:
     * - Used during persistent token auto-login and post-registration session hydration. In `GlobalGateway` mode, unauthenticated token rotation occurs before session tenant binding, requiring primary-key lookup without landing-host tenant filtering.
     */
    public function findById(int $id): ?AuthUser
    {
        $sql = "SELECT id, name, email, role, tenant_id FROM users WHERE id = ?";
        $params = [$id];

        if ($this->authMode === AuthTenantMode::TenantScoped) {
            $tenantId = $this->getTenantId();
            if ($tenantId !== null) {
                $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)";
                $params[] = $tenantId;
            }
        }

        $row = $this->fetchOne($sql, $params);
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
        $tenantId = $this->getTenantId();
        $sql = "SELECT password_changed_at FROM users WHERE id = ?";
        $params = [$id];

        if ($tenantId !== null) {
            $sql .= " AND (tenant_id = ? OR tenant_id IS NULL)";
            $params[] = $tenantId;
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
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
