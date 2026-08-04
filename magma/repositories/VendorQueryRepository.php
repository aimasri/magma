<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\VendorQueryInterface;

use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Magma\database\BaseQueryRepository;
use Magma\dto\VendorDTO;

/**
 * Title: Vendor Query Repository
 * Purpose:
 * - Implements the database read operations for Vendor entities.
 * - Coordinates with the read-replica database connections (via BaseQueryRepository).
 * - Maps raw database rows into VendorDTO domain objects.
 * Why/Why this design:
 * - Adheres to CQRS by exclusively handling read operations.
 * - Returns DTOs (Data Transfer Objects) instead of active records to prevent accidental writes or unintended lazy-loading in the presentation layer.
 * Teaching notes:
 * - Notice the use of generators (`yield`) in `getAll`. This is an industry best practice for handling potentially large datasets efficiently without loading everything into memory at once.
 */
class VendorQueryRepository extends BaseQueryRepository implements VendorQueryInterface
{
    private int $primaryVendorId;
    private VendorMapper $mapper;

    public function __construct(
        DatabaseConnectionManager $dbManager,
        TenantContext $tenantContext,
        VendorMapper $mapper,
        int $primaryVendorId = 1
    ) {
        parent::__construct($dbManager, $tenantContext);
        $this->mapper = $mapper;
        $this->primaryVendorId = $primaryVendorId;
    }


    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        $sql = "SELECT id, name, tagline, email, plan_id, subscription_status, billing_cycle_anchor, payment_gateway_customer_id, theme_settings FROM vendors";
        if ($lastId !== null) {
            $sql .= " WHERE id > :last_id";
        }
        $sql .= " ORDER BY id ASC LIMIT :limit";
        
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        if ($lastId !== null) {
            $stmt->bindValue(':last_id', $lastId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            yield $this->mapper->toDomain($row);
        }
    }

    public function find(int $id): ?VendorDTO
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, tagline, email, plan_id, subscription_status, billing_cycle_anchor, payment_gateway_customer_id, theme_settings FROM vendors WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $vendor = $stmt->fetch() ?: null;
        return $vendor ? $this->mapper->toDomain($vendor) : null;
    }

    public function getPrimaryVendor(): ?VendorDTO
    {
        return $this->find($this->primaryVendorId);
    }
}
