<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\http\RequestInterface;
use Magma\database\DatabaseConnectionManager;
use PDO;
use Throwable;

/**
 * Title: Domain Tenant Context Provider
 *
 * Purpose:
 * - Resolves the active tenant ID by mapping the incoming HTTP request's host/domain against the database.
 * - Adheres to zero-trust multi-tenancy by strictly falling back to `null` if no match is found, ensuring Magma remains agnostic.
 *
 * Why / Why this design:
 * - Strategy Pattern: Plugs seamlessly into `TenantSecurityMiddleware` without modifying core routing logic.
 * - Defensive Data Access: Queries the `tenant_domains` table safely using the read-replica to prevent blocking writes. Gracefully handles database unavailability.
 *
 * Teaching notes:
 * - Returning `null` triggers framework-level fallback behavior (e.g., throwing a 404 or falling back to default Magma branding on errors).
 */
class DomainTenantContextProvider implements TenantContextProviderInterface
{
    private DatabaseConnectionManager $dbManager;

    public function __construct(DatabaseConnectionManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Resolves the primary tenant ID from the request domain.
     *
     * @param RequestInterface $request
     * @return int|null
     */
    public function resolveTenantId(RequestInterface $request): ?int
    {
        $host = $request->server('HTTP_HOST');
        if (empty($host) || !is_scalar($host)) {
            return null;
        }

        // Clean port if present (e.g. localhost:8080 -> localhost)
        $domain = explode(':', (string)$host)[0];

        try {
            $db = $this->dbManager->getReadConnection();
            $sql = "SELECT tenant_id FROM tenant_domains WHERE domain = :domain LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute(['domain' => $domain]);
            
            $result = $stmt->fetchColumn();
            
            return $result !== false ? (int)$result : null;
        } catch (Throwable $e) {
            error_log("Failed to resolve tenant from domain: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolves an optional sub-venue or location ID from the request.
     *
     * @param RequestInterface $request
     * @return int|null
     */
    public function resolveVenueId(RequestInterface $request): ?int
    {
        return null;
    }
}
