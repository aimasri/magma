<?php

namespace Magma\repositories;
use Magma\interfaces\cqrs\VendorCommandInterface;

use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Magma\models\AbstractCommandRepository;

/**
 * Title: Vendor Command Repository
 * Purpose:
 * - Implements the actual database write operations for Vendor entities.
 * - Handles the creation, updating, and deletion of vendor records.
 * - Coordinates with the VendorMapper to translate domain data into database-ready formats.
 * Why/Why this design:
 * - Follows the Repository pattern focused strictly on Commands (CQRS), ensuring write operations are isolated.
 * - Utilizes a Mapper to keep the repository unaware of complex object structures, maintaining a single responsibility (database interaction).
 * Teaching notes:
 * - This class directly manipulates the write database connection. It should not contain business logic, only data mapping and persistence execution.
 */
class VendorCommandRepository extends AbstractCommandRepository implements VendorCommandInterface
{
    private VendorMapper $mapper;

    public function __construct(
        DatabaseConnectionManager $dbManager,
        TenantContext $tenantContext,
        VendorMapper $mapper
    ) {
        parent::__construct($dbManager, $tenantContext);
        $this->mapper = $mapper;
    }



    public function create(array $data): bool
    {
        $bindings = $this->mapper->toDatabase($data);

        if (empty($bindings)) {
            return false;
        }

        $fields = array_keys($bindings);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = sprintf(
            "INSERT INTO vendors (%s) VALUES (%s)",
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function update(int $id, array $data): bool
    {
        $bindings = $this->mapper->toDatabase($data);

        if (empty($bindings)) {
            return false;
        }

        $setClauses = [];
        foreach ($bindings as $column => $value) {
            $setClauses[] = "$column = :$column";
        }

        $bindings['id'] = $id;

        $sql = sprintf(
            "UPDATE vendors SET %s WHERE id = :id",
            implode(', ', $setClauses)
        );

        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->getDb()->prepare("DELETE FROM vendors WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
