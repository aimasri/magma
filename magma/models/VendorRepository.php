<?php

namespace Magma\models;

use PDO;

/**
 * Vendor Configuration Data Access
 *
 * Purpose:
 * - Encapsulate SQL operations for vendor metadata, including configuration and theming.
 * - Ensure presentation code relies on a simple PHP array model rather than raw database cursors.
 *
 * Why / Why this design:
 * - Following the Repository pattern ensures that if the schema of the `vendors` table 
 *   changes, the only class that needs modification is this one.
 * - Because it implements `VendorRepositoryInterface`, caching can be added externally 
 *   via decorators without polluting this file's SQL logic.
 *
 * Teaching notes:
 * - The `create` and `update` methods use a dynamic query generation approach. Parameter 
 *   validation and hydration are explicitly delegated to the injected `VendorMapper`, adhering 
 *   to the Single Responsibility Principle and keeping this class purely focused on SQL mechanics.
 */
class VendorRepository extends BaseRepository implements VendorRepositoryInterface
{
    private int $primaryVendorId;
    private VendorMapper $mapper;

    public function __construct(PDO $dbWrite, PDO $dbRead, VendorMapper $mapper, int $primaryVendorId = 1)
    {
        parent::__construct($dbWrite, $dbRead);
        $this->mapper = $mapper;
        $this->primaryVendorId = $primaryVendorId;
    }

    /**
     * Create a New Vendor Record
     *
     * Purpose:
     * - Inserts a new vendor configuration into the database.
     *
     * Execution Flow:
     * 1. Delegates to `VendorMapper::toDatabase` to sanitize inputs and serialize JSON.
     * 2. Dynamically constructs the parameterized INSERT statement from the returned bindings.
     * 3. Executes the query.
     *
     * @param array $data Associative array of vendor properties.
     * @return bool True on success, false on failure or if no valid fields provided.
     */
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

        $stmt = $this->dbWrite->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Retrieve All Vendors
     *
     * Purpose:
     * - Fetches a paginated list of all vendors in the system.
     *
     * Logic behind the logic:
     * - Uses a generator (`yield`) to stream results one-by-one, maintaining O(1) memory 
     *   complexity even when fetching massive vendor lists. Each row is seamlessly hydrated 
     *   to convert JSON `theme_settings` back into a PHP array on the fly.
     *
     * @param int $limit Maximum number of records to return.
     * @param int|null $lastId The ID cursor from the last page (keyset pagination). Pass null to start from the beginning.
     * @return iterable Array of hydrated vendor records.
     */
    public function getAll(int $limit = 100, ?int $lastId = null): iterable
    {
        $sql = "SELECT id, name, tagline, email, plan_id, subscription_status, billing_cycle_anchor, payment_gateway_customer_id, theme_settings FROM vendors";
        if ($lastId !== null) {
            $sql .= " WHERE id > :last_id";
        }
        $sql .= " ORDER BY id ASC LIMIT :limit";
        
        $stmt = $this->dbRead->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($lastId !== null) {
            $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            yield $this->mapper->toDomain($row);
        }
    }

    /**
     * Find a Vendor by ID
     *
     * Purpose:
     * - Fetches a specific vendor record by its primary key.
     *
     * @param int $id The vendor's primary key.
     * @return array|null The hydrated vendor array, or null if not found.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->dbRead->prepare("SELECT id, name, tagline, email, plan_id, subscription_status, billing_cycle_anchor, payment_gateway_customer_id, theme_settings FROM vendors WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $vendor = $stmt->fetch() ?: null;
        return $vendor ? $this->mapper->toDomain($vendor) : null;
    }



    /**
     * Update an Existing Vendor
     *
     * Purpose:
     * - Modifies specific fields of an existing vendor record.
     *
     * Logic behind the logic:
     * - Identical to `create()`, it delegates data mapping and sanitization to `VendorMapper`, 
     *   which prevents mass-assignment vulnerabilities and handles JSON serialization.
     *
     * @param int $id The vendor's primary key.
     * @param array $data Associative array of fields to update.
     * @return bool True on success, false if no valid fields or query fails.
     */
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

        // Add the ID to the bindings array for the WHERE clause
        $bindings['id'] = $id;

        $sql = sprintf(
            "UPDATE vendors SET %s WHERE id = :id",
            implode(', ', $setClauses)
        );

        $stmt = $this->dbWrite->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Delete a Vendor
     *
     * Purpose:
     * - Hard-deletes a vendor record from the database.
     *
     * Teaching notes:
     * - Because `vendor_id` is a foreign key configured with `ON DELETE CASCADE` in most 
     *   child tables, this action will automatically purge all associated inventory, 
     *   recipes, and staff roles without requiring application-level cleanup.
     *
     * @param int $id The vendor's primary key.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM vendors WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get the Primary Platform Vendor
     *
     * Purpose:
     * - Fetches the vendor representing the core platform (typically ID 1).
     *
     * @return array|null The hydrated primary vendor array.
     */
    public function getPrimaryVendor(): ?array
    {
        return $this->find($this->primaryVendorId);
    }

}
